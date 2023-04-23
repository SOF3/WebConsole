use defy::defy;
use yew::prelude::*;

use crate::api;
use crate::i18n::I18n;
use crate::util::Grc;

#[function_component]
pub fn Comp(props: &Props) -> Html {
    let Some(api) = props
        .discovery
        .apis
        .get(&api::GroupKindRef { group: props.group.as_str(), kind: props.kind.as_str() }
            as &dyn api::GroupKindDyn)
    else {
        return defy! { + "Error: unknown API"; }
    };

    let gk_display_name = props.i18n.disp(&api.display_name);
    use_effect_with_deps(
        {
            let title = format!("{gk_display_name} {}", props.name);
            move |_| {
                gloo::utils::document().set_title(&title);
            }
        },
        (props.group.clone(), props.kind.clone(), props.name.clone()),
    );

    defy! {
        h1 {
            + format!("{gk_display_name} {}", props.name);
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub api:       Grc<api::Client>,
    pub i18n:      I18n,
    pub discovery: Grc<api::Discovery>,
    pub group:     AttrValue,
    pub kind:      AttrValue,
    pub name:      AttrValue,
}
