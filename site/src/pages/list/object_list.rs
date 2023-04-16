use std::cmp;
use std::collections::{BTreeMap, HashSet};

use anyhow::Context as _;
use defy::defy;
use futures::channel::oneshot;
use futures::StreamExt;
use wasm_bindgen_futures::spawn_local;
use yew::prelude::*;

use super::DisplayMode;
use crate::i18n::I18n;
use crate::util::{self, Grc, RcStr};
use crate::{api, comps};

pub struct ObjectList {
    objects:     BTreeMap<String, api::Object>,
    err:         Option<String>,
    shutdown_tx: Option<oneshot::Sender<()>>,
}

fn run_watch(ctx: &Context<ObjectList>, comp: &mut ObjectList) {
    let (shutdown_tx, mut shutdown_rx) = oneshot::channel::<()>();

    let callback = ctx.link().callback(|event| ObjectListMsg::Event(event));
    let props = ctx.props();

    spawn_local({
        let api = props.api.clone();
        let group = props.group.to_string();
        let kind = props.kind.to_string();
        async move {
            match async {
                let mut stream = api.watch(group, kind).await.context("watch for objects")?.fuse();

                loop {
                    let event: anyhow::Result<api::ObjectEvent> = futures::select! {
                        event = stream.next() => match event {
                            Some(event) => event,
                            None => return Ok(()),
                        },
                        _ = shutdown_rx => return Ok(()),
                    };
                    callback.emit(event);
                }
            }
            .await
            {
                Ok(()) => (),
                Err(err) => callback.emit(Err(err)),
            }
        }
    });

    if let Some(old) = comp.shutdown_tx.replace(shutdown_tx) {
        _ = old.send(());
    }

    comp.objects.clear();
}

impl Component for ObjectList {
    type Message = ObjectListMsg;
    type Properties = ObjectListProps;

    fn create(ctx: &Context<Self>) -> Self {
        let mut obj = Self { objects: BTreeMap::new(), err: None, shutdown_tx: None };

        run_watch(ctx, &mut obj);

        obj
    }

    fn destroy(&mut self, _ctx: &Context<Self>) {
        if let Some(shutdown_tx) = self.shutdown_tx.take() {
            _ = shutdown_tx.send(());
        }
    }

    fn update(&mut self, _ctx: &Context<Self>, msg: Self::Message) -> bool {
        match msg {
            ObjectListMsg::Event(Ok(event)) => {
                match event {
                    api::ObjectEvent::Clear => {
                        self.objects.clear();
                    }
                    api::ObjectEvent::Added { item: object } => {
                        self.objects.insert(object.name.clone(), object);
                    }
                    api::ObjectEvent::Removed { name } => {
                        self.objects.remove(&*name);
                    }
                    api::ObjectEvent::FieldUpdate { name, field, value } => {
                        let Some(object) = self.objects.get_mut(&*name) else { return false };
                        if let Err(err) = util::set_json_path(&mut object.fields, &*field, value) {
                            log::warn!("invalid json path: {err:?}");
                        }
                    }
                }
                true
            }
            ObjectListMsg::Event(Err(err)) => {
                self.err = Some(format!("{err:?}"));
                true
            }
        }
    }

    fn changed(&mut self, ctx: &Context<Self>, old_props: &Self::Properties) -> bool {
        if ctx.props().group != old_props.group || ctx.props().kind != old_props.kind {
            run_watch(ctx, self);
        }

        true
    }

    fn view(&self, ctx: &Context<Self>) -> Html {
        let hidden = &ctx.props().hidden;
        let mut fields: Vec<_> = ctx
            .props()
            .def
            .fields
            .values()
            .filter(|&field| !hidden.contains(&field.path))
            .collect();
        fields.sort_by_key(|field| (cmp::Reverse(field.metadata.display_priority), &field.path));

        let i18n = &ctx.props().i18n;

        defy! {
            if let Some(err) = &self.err {
                article(class = "message is-danger") {
                    div(class = "message-header") {
                        p {
                            + "Error";
                        }
                    }
                    div(class = "message-body") {
                        pre {
                            + err;
                        }
                    }
                }
            }

            match ctx.props().display_mode {
                DisplayMode::Cards => {
                    div {
                        for object in self.objects.values() {
                            div(class = "card object-thumbnail") {
                                if !ctx.props().def.metadata.hide_name {
                                    header(class = "card-header") {
                                        p(class = "card-header-title") {
                                            + &object.name;
                                        }
                                    }
                                }

                                if !fields.is_empty() {
                                    div(class = "card-content") {
                                        div(class = "content") {
                                            for &field in &fields {
                                                if let Some(value) = util::get_json_path(&object.fields, &field.path) {
                                                    span(class = "tag") {
                                                        + i18n.disp(&field.display_name);
                                                    }

                                                    comps::InlineDisplay(
                                                        i18n = i18n.clone(),
                                                        value = value.clone(),
                                                        ty = field.ty.clone(),
                                                    );
                                                    + " ";
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                DisplayMode::Table => {
                    table(class = "table") {
                        thead {
                            tr {
                                if !ctx.props().def.metadata.hide_name {
                                    th { + i18n.disp("base-name"); }
                                }

                                for field in &fields {
                                    th { + i18n.disp(&field.display_name); }
                                }
                            }
                        }
                        tbody {
                            for object in self.objects.values() {
                                tr {
                                    if !ctx.props().def.metadata.hide_name {
                                        th {
                                            + &object.name;
                                        }
                                    }

                                    for field in &fields {
                                        td {
                                            if let Some(value) = util::get_json_path(&object.fields, &field.path) {
                                                comps::InlineDisplay(
                                                    i18n = i18n.clone(),
                                                    value = value.clone(),
                                                    ty = field.ty.clone(),
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

pub enum ObjectListMsg {
    Event(anyhow::Result<api::ObjectEvent>),
}

#[derive(Clone, PartialEq, Properties)]
pub struct ObjectListProps {
    pub api:          Grc<api::Client>,
    pub i18n:         I18n,
    pub group:        AttrValue,
    pub kind:         AttrValue,
    pub def:          api::Desc,
    pub hidden:       HashSet<RcStr>,
    pub display_mode: DisplayMode,
}
