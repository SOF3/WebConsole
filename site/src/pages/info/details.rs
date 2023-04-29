use std::collections::BTreeMap;

use anyhow::Context as _;
use defy::defy;
use futures::channel::oneshot;
use futures::StreamExt;
use wasm_bindgen_futures::spawn_local;
use yew::prelude::*;

use crate::api;
use crate::i18n::I18n;
use crate::util::{self, Grc};

pub struct Comp {
    objects:     BTreeMap<String, api::Object>,
    err:         Option<String>,
    shutdown_tx: Option<oneshot::Sender<()>>,
}

fn run_watch(ctx: &Context<Comp>, comp: &mut Comp) {
    let (shutdown_tx, mut shutdown_rx) = oneshot::channel::<()>();

    let callback = ctx.link().callback(|event| Msg::Event(event));
    let props = ctx.props();

    spawn_local({
        let api = props.api.clone();
        let group = props.group.to_string();
        let kind = props.kind.to_string();
        let name = props.name.to_string();
        async move {
            match async {
                let mut stream =
                    api.watch_single(group, kind, name).await.context("watch for objects")?.fuse();

                loop {
                    let event: anyhow::Result<api::WatchListEvent> = futures::select! {
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

fn iter_map_order(
    map: &BTreeMap<String, api::Object>,
    desc: bool,
) -> impl Iterator<Item = &api::Object> {
    if desc {
        Box::new(map.values().rev())
    } else {
        Box::new(map.values()) as Box<dyn Iterator<Item = _>>
    }
}

impl Component for Comp {
    type Message = Msg;
    type Properties = Props;

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
            Msg::Event(Ok(event)) => {
                match event {
                    api::WatchListEvent::Clear => {
                        self.objects.clear();
                    }
                    api::WatchListEvent::Added { item: object } => {
                        self.objects.insert(object.name.clone(), object);
                    }
                    api::WatchListEvent::Removed { name } => {
                        self.objects.remove(&*name);
                    }
                    api::WatchListEvent::FieldUpdate { name, field, value } => {
                        let Some(object) = self.objects.get_mut(&*name) else { return false };
                        if let Err(err) = util::set_json_path(&mut object.fields, &*field, value) {
                            log::warn!("invalid json path: {err:?}");
                        }
                    }
                }
                true
            }
            Msg::Event(Err(err)) => {
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
        let def = &ctx.props().def;
        let i18n = &ctx.props().i18n;

        defy! {}
    }
}

pub enum Msg {
    Event(anyhow::Result<api::WatchListEvent>),
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub api:       Grc<api::Client>,
    pub i18n:      I18n,
    pub discovery: Grc<api::Discovery>,
    pub def:       api::Desc,
    pub group:     AttrValue,
    pub kind:      AttrValue,
    pub name:      AttrValue,
}
