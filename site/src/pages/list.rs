use defy::defy;
use yew::prelude::*;

use crate::api;
use crate::i18n::I18n;
use crate::util::Grc;

#[function_component]
pub fn Comp(props: &Props) -> Html {
    let api = match props.discovery.apis.get(props.id.as_str()) {
        Some(api) => api,
        None => return defy! { + "Error: unknown API"; },
    };

    let list_display_name = props.i18n.disp(&api.display_name);
    use_effect_with_deps(
        {
            let list_display_name = list_display_name.clone();
            move |_| {
                gloo::utils::document().set_title(&list_display_name);
            }
        },
        props.id.clone(),
    );

    defy! {
        h1(class = "title") {
            + props.i18n.disp(&api.display_name);
        }
    }
}

#[derive(PartialEq, Properties)]
pub struct Props {
    pub i18n:      I18n,
    pub discovery: Grc<api::Discovery>,
    pub id:        AttrValue,
}
