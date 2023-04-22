use defy::defy;
use fluent::fluent_args;
use yew::prelude::*;

use crate::api;
use crate::i18n::I18n;

fn round(number: f64) -> f64 { (number * 10.).round() / 10. }

#[function_component]
pub fn InlineDisplay(props: &Props) -> Html {
    defy! {
        match &props.ty {
            api::FieldType::String {} => {
                match &props.value {
                    serde_json::Value::String(string) => { + string; }
                    _ => { + "invalid value"; }
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
                    _ => { + "invalid value"; }
                }
            }
            api::FieldType::Bool {} => {
                match &props.value {
                    serde_json::Value::Bool(bool) => { + bool; }
                    _ => { + "invalid value"; }
                }
            }
            api::FieldType::Enum { options } => {
                let option = match &props.value {
                    serde_json::Value::String(string) => options.get(string.as_str()),
                    _ => None,
                };
                match option {
                    None => { + "invalid value"; }
                    Some(option) => { + props.i18n.disp(&option.i18n); }
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
                            + props.i18n.disp_with("base-list-item-count-prefix", fluent_args!["count" => array.len()]);
                            for element in array.iter().take(3) {
                                InlineDisplay(
                                    i18n = props.i18n.clone(),
                                    value = element.clone(),
                                    ty = (&**item).clone(),
                                    nested = true,
                                );
                            }
                        }
                    }
                    _ => { + "invalid value"; }
                }
            }
            api::FieldType::Compound { fields } => {
                let map = match &props.value {
                    serde_json::Value::Null => Some(serde_json::Map::default()),
                    serde_json::Value::Array(array) if array.is_empty() => Some(serde_json::Map::default()),
                    serde_json::Value::Object(map) if map.is_empty() => Some(serde_json::Map::default()),
                    serde_json::Value::Object(map) => Some(map.clone()),
                    _ => None,
                };

                match map {
                    Some(map) if map.is_empty() => {
                        span(class = "is-italic has-text-weight-light") {
                            + props.i18n.disp("base-compound-empty");
                        }
                    }
                    Some(map) => {
                        if props.nested {
                            + "{\u{2026}}";
                        } else {
                            for field in fields.values() {
                                span(class = "tag is-info is-light") {
                                    + props.i18n.disp(&field.name);
                                }
                                InlineDisplay(
                                    i18n = props.i18n.clone(),
                                    value = map.get(field.key.as_str()).cloned().unwrap_or(serde_json::Value::Null),
                                    ty = field.ty.clone(),
                                    nested = true,
                                );
                            }
                        }
                    }
                    None => { + "invalid value"; }
                }
            }
            api::FieldType::Object { gk } => {
                match &props.value {
                    serde_json::Value::String(name) => {
                        a(
                            href = format!("/{}/{}/{}", &gk.group, &gk.kind, name),
                        ) {
                            + name;
                        }
                    }
                    _ => { + "invalid value"; }
                }
            }
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
