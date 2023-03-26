use std::collections::{BTreeMap, HashSet};

use anyhow::Context as _;
use defy::defy;
use futures::StreamExt;
use wasm_bindgen_futures::spawn_local;
use yew::prelude::*;

use crate::i18n::I18n;
use crate::util::{self, Grc, RcStr};
use crate::{api, comps};

pub struct ObjectList {
    objects: BTreeMap<String, api::Object>,
    err:     Option<String>,
}

impl Component for ObjectList {
    type Message = ObjectListMsg;
    type Properties = ObjectListProps;

    fn create(ctx: &Context<Self>) -> Self {
        let props = ctx.props();

        let callback = ctx.link().callback(|event| ObjectListMsg::Event(event));
        spawn_local({
            let api = props.api.clone();
            let group = props.group.to_string();
            let kind = props.kind.to_string();
            async move {
                match async {
                    let mut stream = api.watch(group, kind).await.context("watch for objects")?;

                    while let Some(event) = stream.next().await {
                        callback.emit(event);
                    }

                    Ok(())
                }
                .await
                {
                    Ok(()) => (),
                    Err(err) => callback.emit(Err(err)),
                }
            }
        });

        Self { objects: BTreeMap::new(), err: None }
    }

    fn update(&mut self, _ctx: &Context<Self>, msg: Self::Message) -> bool {
        match msg {
            ObjectListMsg::Event(Ok(event)) => {
                match event {
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

    fn view(&self, ctx: &Context<Self>) -> Html {
        let hidden = &ctx.props().hidden;
        let fields: Vec<_> = ctx
            .props()
            .def
            .fields
            .values()
            .filter(|&field| !hidden.contains(&field.path))
            .collect();
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

            div {
                for object in self.objects.values() {
                    div(class = "card object-thumbnail") {
                        header(class = "card-header") {
                            p(class = "card-header-title") {
                                + &object.name;
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
    }
}

pub enum ObjectListMsg {
    Event(anyhow::Result<api::ObjectEvent>),
}

#[derive(Clone, PartialEq, Properties)]
pub struct ObjectListProps {
    pub api:    Grc<api::Client>,
    pub i18n:   I18n,
    pub group:  AttrValue,
    pub kind:   AttrValue,
    pub def:    api::Desc,
    pub hidden: HashSet<RcStr>,
}
