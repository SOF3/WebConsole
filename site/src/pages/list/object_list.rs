use std::cmp;
use std::collections::{BTreeMap, HashSet};
use std::pin::Pin;
use std::rc::Rc;

use anyhow::Result;
use defy::defy;
use futures::stream::FusedStream;
use futures::{Future, StreamExt};
use yew::prelude::*;
use yew_router::prelude::*;

use super::DisplayMode;
use crate::comps::watch_loader;
use crate::i18n::I18n;
use crate::util::{self, Grc, RcStr};
use crate::{api, comps, Route};

pub struct ObjectStore {
    objects: BTreeMap<String, api::Object>,
}

impl watch_loader::State for ObjectStore {
    type Input = ObjectListProps;
    fn new(_input: &Self::Input) -> Self { Self { objects: BTreeMap::new() } }

    fn reset(&mut self) { self.objects.clear(); }

    type Deps<'t> = api::GroupKind;
    fn deps<'t>(input: &'t Self::Input) -> Self::Deps<'t> {
        api::GroupKind {
            group: RcStr::from(input.group.clone()),
            kind:  RcStr::from(input.kind.clone()),
        }
    }

    type Event = api::WatchListEvent;
    fn update(&mut self, msg: Self::Event) -> watch_loader::NeedRender {
        match msg {
            api::WatchListEvent::Clear => {
                self.objects.clear();
                true
            }
            api::WatchListEvent::Added { item: object } => {
                self.objects.insert(object.name.clone(), object);
                true
            }
            api::WatchListEvent::Removed { name } => {
                self.objects.remove(&*name);
                true
            }
            api::WatchListEvent::FieldUpdate { name, field, value } => {
                let Some(object) = self.objects.get_mut(&*name) else { return false };
                if let Err(err) = util::set_json_path(&mut object.fields, &*field, value) {
                    log::warn!("invalid json path: {err:?}");
                }
                true
            }
        }
    }

    fn watch(
        &self,
        props: &ObjectListProps,
    ) -> Pin<
        Box<dyn Future<Output = Result<Box<dyn FusedStream<Item = Result<Self::Event>> + Unpin>>>>,
    > {
        let api = props.api.clone();
        let group = props.group.to_string();
        let kind = props.kind.to_string();
        Box::pin(async move {
            let stream = api.watch_list(group, kind).await?;
            Ok(Box::new(stream.fuse()) as Box<dyn FusedStream<Item = _> + Unpin + 'static>)
        })
    }
}

#[function_component]
pub fn ObjectList(props: &ObjectListProps) -> Html {
    let closure = {
        let props = props.clone();

        move |state: &ObjectStore| {
            let def = &props.def;
            let hidden = &props.hidden;
            let fields = {
                let mut fields = def
                    .fields
                    .values()
                    .filter(|&field| !hidden.contains(&field.path))
                    .collect::<Vec<_>>();
                fields.sort_by_key(|field| {
                    (cmp::Reverse(field.metadata.display_priority), &field.path)
                });
                fields
            };

            let i18n = &props.i18n;
            let display_mode = props.display_mode;

            let display = match display_mode {
                DisplayMode::Cards => display_cards,
                DisplayMode::Table => display_table,
            };
            display(state, def, &fields, i18n)
        }
    };

    defy! {
        watch_loader::Comp<ObjectStore>(
            input = props.clone(),
            body = Rc::new(closure) as Rc<dyn Fn(&ObjectStore) -> Html>,
        );
    }
}

fn display_cards(
    state: &ObjectStore,
    def: &api::ObjectDef,
    fields: &[&api::FieldDef],
    i18n: &I18n,
) -> Html {
    defy! {
        div {
            for object in iter_map_order(&state.objects, def.metadata.desc_name) {
                div(class = "card object-thumbnail") {
                    if !def.metadata.hide_name {
                        header(class = "card-header") {
                            Link<Route>(to = Route::Info { group: def.id.group.clone(), kind: def.id.kind.clone(), name: (&object.name).into() }) {
                                p(class = "card-header-title") {
                                    + &object.name;
                                }
                            }
                        }
                    }

                    if !fields.is_empty() {
                        div(class = "card-content") {
                            div(class = "content") {
                                for &field in fields {
                                    let value = util::get_json_path(&object.fields, &field.path).cloned().unwrap_or(serde_json::Value::Null);

                                    div(class = "is-inline-block mx-2") {
                                        span(class = "tag is-primary is-medium mr-1") {
                                            + i18n.disp(&field.display_name);
                                        }

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

fn display_table(
    state: &ObjectStore,
    def: &api::ObjectDef,
    fields: &[&api::FieldDef],
    i18n: &I18n,
) -> Html {
    defy! {
        table(class = "table") {
            thead {
                tr {
                    if !def.metadata.hide_name {
                        th { + i18n.disp("base-name"); }
                    }

                    for field in fields {
                        th { + i18n.disp(&field.display_name); }
                    }
                }
            }
            tbody {
                for object in iter_map_order(&state.objects, def.metadata.desc_name) {
                    Link<Route>(
                        classes = "undecorate-hyperlink",
                        to = Route::Info { group: def.id.group.clone(), kind: def.id.kind.clone(), name: (&object.name).into() },
                    ) {
                        tr {
                            if !def.metadata.hide_name {
                                th {
                                    + &object.name;
                                }
                            }

                            for field in fields {
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

#[derive(Clone, PartialEq, Properties)]
pub struct ObjectListProps {
    pub api:          Grc<api::Client>,
    pub i18n:         I18n,
    pub group:        AttrValue,
    pub kind:         AttrValue,
    pub def:          api::ObjectDef,
    pub hidden:       HashSet<RcStr>,
    pub display_mode: DisplayMode,
}
