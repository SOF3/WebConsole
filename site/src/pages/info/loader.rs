use std::cmp;
use std::collections::HashMap;
use std::pin::Pin;
use std::rc::Rc;

use anyhow::Result;
use defy::defy;
use futures::stream::FusedStream;
use futures::{Future, StreamExt};
use yew::prelude::*;

use crate::api;
use crate::comps::watch_loader;
use crate::i18n::I18n;
use crate::util::{Grc, RcStr};

pub struct State {
    fields: HashMap<RcStr, serde_json::Value>,
}

impl watch_loader::State for State {
    type Input = Props;
    fn new(_input: &Self::Input) -> Self { Self { fields: HashMap::new() } }

    fn reset(&mut self) { self.fields.clear(); }

    type Deps<'t> = api::GroupKind;
    fn deps<'t>(input: &'t Self::Input) -> Self::Deps<'t> {
        api::GroupKind {
            group: RcStr::from(input.group.clone()),
            kind:  RcStr::from(input.kind.clone()),
        }
    }

    type Event = api::WatchSingleEvent;
    fn update(&mut self, msg: Self::Event) -> watch_loader::NeedRender {
        match msg {
            api::WatchSingleEvent::Update { field, value } => {
                self.fields.insert(field, value);
                true
            }
        }
    }

    fn watch(
        &self,
        props: &Props,
    ) -> Pin<
        Box<dyn Future<Output = Result<Box<dyn FusedStream<Item = Result<Self::Event>> + Unpin>>>>,
    > {
        let api = props.api.clone();
        let group = props.group.to_string();
        let kind = props.kind.to_string();
        let name = props.name.to_string();
        Box::pin(async move {
            let stream = api.watch_single(group, kind, name).await?;
            Ok(Box::new(stream.fuse()) as Box<dyn FusedStream<Item = _> + Unpin + 'static>)
        })
    }
}

#[function_component]
pub fn Comp(props: &Props) -> Html {
    let closure = {
        let props = props.clone();

        move |state: &State| {
            let def = &props.def;
            let i18n = &props.i18n;

            let fields = {
                let mut fields = def.fields.values().collect::<Vec<_>>();
                fields.sort_by_key(|field| {
                    (cmp::Reverse(field.metadata.display_priority), &field.path)
                });
                fields
            };

            defy! {
                table(class = "table is-hidden-touch") {
                    for &field in &fields {
                        tbody {
                            tr {
                                th {
                                    + i18n.disp(&field.display_name);
                                }
                                td {
                                    + display_object(field, state.fields.get(&field.path).unwrap_or(&serde_json::Value::Null));
                                }
                            }
                        }
                    }
                }
                div(class = "is-hidden-desktop") {
                    for &field in &fields {
                        div(class = "has-text-weight-bold") { +i18n.disp(&field.display_name); }
                        div(class = "pl-1") {
                            div(class = "container") {
                                + display_object(field, state.fields.get(&field.path).unwrap_or(&serde_json::Value::Null));
                            }
                        }
                    }
                }
            }
        }
    };

    defy! {
        watch_loader::Comp<State>(
            input = props.clone(),
            body = Rc::new(closure) as Rc<dyn Fn(&State) -> Html>,
        );
    }
}

fn display_object(field: &api::FieldDef, value: &serde_json::Value) -> Html {
    defy! {
        + "Content";
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub api:   Grc<api::Client>,
    pub i18n:  I18n,
    pub def:   api::ObjectDef,
    pub group: AttrValue,
    pub kind:  AttrValue,
    pub name:  AttrValue,
}
