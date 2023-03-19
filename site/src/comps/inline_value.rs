use defy::defy;
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
            api::FieldType::Int64{} | api::FieldType::Float64 {} => {
                match &props.value {
                    serde_json::Value::Number(number) => {
                        + number.as_f64().map(|v| round(v).to_string())
                            .or_else(|| number.as_u64().map(|v| format!("{v:.1}")))
                            .or_else(|| number.as_i64().map(|v| format!("{v:.1}")))
                            .unwrap_or_else(|| number.to_string());
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
            _ => { +"TODO"; }
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub i18n:  I18n,
    pub value: serde_json::Value,
    pub ty:    api::FieldType,
}
