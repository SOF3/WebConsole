use defy::defy;
use yew::prelude::*;

use crate::i18n::I18n;

#[function_component]
pub fn Comp(props: &Props) -> Html {
    use_effect(|| {
        gloo::utils::document().set_title("Home");
    });

    defy! {
        h1(class = "title") {
            + props.i18n.disp("base-home");
        }
    }
}

#[derive(PartialEq, Properties)]
pub struct Props {
    pub i18n: I18n,
}
