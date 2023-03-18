use defy::defy;
use yew::prelude::*;

use crate::{i18n::I18n, api};

#[function_component]
pub fn InlineDisplay(props: &Props) -> Html {
    defy! {
        match &props.ty {
            api::FieldType::Float64 {} => {
                match &props.value {
                    serde_json::Value::Number(number) => {
                        + format!("{number:.1}");
                    }
                    _ => {
                        + "invalid value";
                    }
                }
            }
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub i18n: I18n,
    pub value: serde_json::Value,
    pub ty: api::FieldType,
}
