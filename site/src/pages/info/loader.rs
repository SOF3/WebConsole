use std::cmp;
use std::collections::HashMap;
use std::pin::Pin;
use std::rc::Rc;

use anyhow::Result;
use defy::defy;
use futures::stream::FusedStream;
use futures::{Future, StreamExt};
use wasm_bindgen::JsValue;
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
                                    + display_object(field, state.fields.get(&field.path).unwrap_or(&serde_json::Value::Null)).1;
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
                                + display_object(field, state.fields.get(&field.path).unwrap_or(&serde_json::Value::Null)).1;
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

#[derive(PartialEq, PartialOrd)]
enum SizeClass {
    Inline,
    Long,
    Structural,
}

fn display_object(field: &api::FieldDef, value: &serde_json::Value) -> (SizeClass, Html) {
    match &field.ty {
        api::FieldType::String {} => match value {
            serde_json::Value::String(value) => (
                if value.contains('\n') { SizeClass::Long } else { SizeClass::Inline },
                defy! {
                    p(class = "content") {
                        + value;
                    }
                },
            ),
            _ => invalid_type("String", value),
        },
        api::FieldType::Int64 { is_timestamp, .. }
        | api::FieldType::Float64 { is_timestamp, .. } => {
            let serde_json::Value::Number(number) = value else {
                return invalid_type("Number", value);
            };
            if *is_timestamp {
                let Some(timestamp) = number.as_f64() else { return invalid_type("Number", value) };
                let time = js_sys::Date::new(&JsValue::from_f64(timestamp));
                let date = js_sys::Date::new(&wasm_bindgen::JsValue::from_f64(timestamp / 1000.0));
                let time = format!(
                    "{:0>2}:{:0>2}:{:0>2}",
                    date.get_hours(),
                    date.get_minutes(),
                    date.get_seconds()
                );
                (
                    SizeClass::Inline,
                    defy! {p(class = "content") {
                        + time;
                    }},
                )
            } else {
                let minmax = match &field.ty {
                    &api::FieldType::Int64 { min: Some(min), max: Some(max), .. } => {
                        let Some(value) = number.as_i64() else { return invalid_type("Number", value) };
                        Some(((value - min) as f64) / ((max - min) as f64))
                    }
                    &api::FieldType::Float64 { min: Some(min), max: Some(max), .. } => {
                        let Some(value) = number.as_f64() else { return invalid_type("Number", value) };
                        Some((value - min) / (max - min))
                    }
                    _ => None,
                };
                match minmax {
                    Some(ratio) => (
                        SizeClass::Long,
                        defy! {
                            p(class = "content") {
                                + value;
                            }
                            progress(
                                class = "progress is-primary",
                                value = ratio.to_string(),
                            ) {
                                + format!("{}%", ratio * 100.);
                            }
                        },
                    ),
                    None => (
                        SizeClass::Inline,
                        defy! {
                            p(class = "content") {
                                + value;
                            }
                        },
                    ),
                }
            }
        }
        api::FieldType::Bool {} => {
            let serde_json::Value::Bool(value) = value else {
                return invalid_type("Bool", value);
            };
            (
                SizeClass::Inline,
                defy! {
                    input(
                        type = "checkbox",
                        checked = *value,
                    );
                },
            )
        }
        api::FieldType::Object { .. } => (SizeClass::Inline, defy! { + "TODO"; }),
        api::FieldType::Enum { .. } => (SizeClass::Inline, defy! { + "TODO"; }),
        api::FieldType::Nullable { .. } => (SizeClass::Inline, defy! { + "TODO"; }),
        api::FieldType::List { .. } => (SizeClass::Inline, defy! { + "TODO"; }),
        api::FieldType::Compound { .. } => (SizeClass::Inline, defy! { + "TODO"; }),
    }
}

fn invalid_type(expect: &str, got: &serde_json::Value) -> (SizeClass, Html) {
    let (got_ty, is_short) = match got {
        serde_json::Value::Null => ("Null", false),
        serde_json::Value::Bool(..) => ("Bool", true),
        serde_json::Value::Number(..) => ("Number", true),
        serde_json::Value::String(..) => ("String", true),
        serde_json::Value::Array(..) => ("Array", false),
        serde_json::Value::Object(..) => ("Object", false),
    };
    let html = defy! {
        span(class = "has-text-danger") {
            + format!("expected {expect}, got {got_ty}");
            if is_short {
                + format!(" ({got:?})");
            }
        }
    };
    (SizeClass::Long, html)
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
