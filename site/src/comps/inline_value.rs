use defy::defy;
use fluent::fluent_args;
use yew::prelude::*;
use yew_router::prelude::*;

use crate::i18n::I18n;
use crate::{api, Route};

fn round(number: f64) -> f64 { (number * 10.).round() / 10. }

const LIST_DISPLAY_LIMIT: usize = 3;

#[function_component]
pub fn InlineDisplay(props: &Props) -> Html {
    defy! {
        match &props.ty {
            api::FieldType::String {} => {
                match &props.value {
                    serde_json::Value::String(string) => { + string; }
                    v => { + invalid_type("String", v); }
                }
            }
            api::FieldType::Int64{ is_timestamp, .. } | api::FieldType::Float64 { is_timestamp, .. } => {
                match &props.value {
                    serde_json::Value::Number(number) => {
                        if *is_timestamp {
                            match number.as_f64() {
                                Some(number) => {
                                    let date = js_sys::Date::new(&wasm_bindgen::JsValue::from_f64(number / 1000.0));
                                    +format!("{:0>2}:{:0>2}:{:0>2}", date.get_hours(), date.get_minutes(), date.get_seconds());
                                }
                                None => {
                                    +"invalid timestamp";
                                }
                            }
                        } else {
                            + number.as_f64().map(|v| round(v).to_string())
                                .or_else(|| number.as_u64().map(|v| format!("{v:.1}")))
                                .or_else(|| number.as_i64().map(|v| format!("{v:.1}")))
                                .unwrap_or_else(|| number.to_string());
                        }
                    }
                    v => { + invalid_type("Number", v); }
                }
            }
            api::FieldType::Bool {} => {
                match &props.value {
                    serde_json::Value::Bool(bool) => { + bool; }
                    v => { + invalid_type("Bool", v); }
                }
            }
            api::FieldType::Enum { options } => {
                match &props.value {
                    serde_json::Value::String(string) => {
                        match options.get(string.as_str()) {
                            Some(option) => { + props.i18n.disp(&option.i18n); }
                            None => { + "invalid option"; }
                        }
                    }
                    v => { + invalid_type("Bool", v); }
                }
            }
            api::FieldType::Nullable { item } => {
                match &props.value {
                    serde_json::Value::Null => {
                        span(class = "has-text-weight-light icon mdi mdi-null");
                    }
                    _ => {
                        InlineDisplay(
                            i18n = props.i18n.clone(),
                            value = props.value.clone(),
                            ty = (&**item).clone(),
                            nested = false, // this is not visually nested, no need to compact the view.
                        );
                    }
                }
            }
            api::FieldType::List { item } => {
                match &props.value {
                    serde_json::Value::Null => {
                        span(class = "is-italic has-text-weight-light") {
                            + props.i18n.disp("base-list-empty");
                        }
                    }
                    serde_json::Value::Array(array) if array.is_empty() => {
                        span(class = "is-italic has-text-weight-light") {
                            + props.i18n.disp("base-list-empty");
                        }
                    }
                    serde_json::Value::Array(array) => {
                        if props.nested {
                            span(class = "is-italic") {
                                + props.i18n.disp_with("base-list-item-count-nested", fluent_args!["count" => array.len()]);
                            }
                        } else {
                            for element in array.iter().take(LIST_DISPLAY_LIMIT) {
                                div(class = "is-inline-block mx-1") {
                                    InlineDisplay(
                                        i18n = props.i18n.clone(),
                                        value = element.clone(),
                                        ty = (&**item).clone(),
                                        nested = true,
                                    );
                                }
                            }

                            if array.len() > LIST_DISPLAY_LIMIT {
                                span(class = "tag is-info") {
                                    + props.i18n.disp_with("base-list-item-count-remainder", fluent_args!["count" => array.len() - LIST_DISPLAY_LIMIT]);
                                }
                            }
                        }
                    }
                    v => { + invalid_type("List", v); }
                }
            }
            api::FieldType::Compound { fields } => {
                let map = match &props.value {
                    serde_json::Value::Null => Ok(serde_json::Map::default()),
                    serde_json::Value::Array(array) if array.is_empty() => Ok(serde_json::Map::default()),
                    serde_json::Value::Object(map) if map.is_empty() => Ok(serde_json::Map::default()),
                    serde_json::Value::Object(map) => Ok(map.clone()),
                    v => Err(v),
                };

                match map {
                    Ok(map) if map.is_empty() => {
                        span(class = "is-italic has-text-weight-light") {
                            + props.i18n.disp("base-compound-empty");
                        }
                    }
                    Ok(map) => {
                        if props.nested {
                            + "{\u{2026}}";
                        } else {
                            for field in fields.values() {
                                span(class = "tag is-info is-light") {
                                    + props.i18n.disp(&field.name);
                                }
                                div(class = "is-inline-block mr-1") {
                                    InlineDisplay(
                                        i18n = props.i18n.clone(),
                                        value = map.get(field.key.as_str()).cloned().unwrap_or(serde_json::Value::Null),
                                        ty = field.ty.clone(),
                                        nested = true,
                                    );
                                }
                            }
                        }
                    }
                    Err(v) => { + invalid_type("Compound", v); }
                }
            }
            api::FieldType::Object { gk } => {
                match &props.value {
                    serde_json::Value::String(name) => {
                        span(class = "tag is-link is-light") {
                            Link<Route>(to = Route::Info { group: gk.group.clone(), kind: gk.kind.clone(), name: name.into() }) {
                                + name;
                            }
                        }
                    }
                    v => { + invalid_type("String", v); }
                }
            }
        }
    }
}

fn invalid_type(expect: &str, got: &serde_json::Value) -> Html {
    let got_ty = match got {
        serde_json::Value::Null => "Null",
        serde_json::Value::Bool(..) => "Bool",
        serde_json::Value::Number(..) => "Number",
        serde_json::Value::String(..) => "String",
        serde_json::Value::Array(..) => "Array",
        serde_json::Value::Object(..) => "Object",
    };
    defy! {
        span(class = "has-text-danger") {
            + format!("expected {expect}, got {got_ty}");
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub i18n:   I18n,
    pub value:  serde_json::Value,
    pub ty:     api::FieldType,
    #[prop_or_default]
    pub nested: bool,
}
