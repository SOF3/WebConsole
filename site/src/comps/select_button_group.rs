use defy::defy;
use yew::prelude::*;

use crate::i18n;

pub trait SelectButtons: Sized + PartialEq + Copy + 'static {
    fn variants() -> &'static [Self];
    fn icon(&self) -> &'static str;
    fn name(&self) -> &'static str;
}

#[function_component]
pub fn SelectButtonGroup<T: SelectButtons>(props: &Props<T>) -> Html {
    defy! {
        div(class = "buttons has-addons") {
            for variant in T::variants() {
                button(
                    class = format!("button {}", if &props.value == variant { "is-selected is-info" } else { "" }),
                    title = props.i18n.disp(variant.name()),
                    onclick = props.callback.reform(|_| *variant),
                ) {
                    span(class = format!("icon mdi {}", variant.icon()));
                }
            }
        }

    }
}

#[derive(PartialEq, Properties)]
pub struct Props<T: SelectButtons> {
    pub i18n:     i18n::I18n,
    pub value:    T,
    pub callback: Callback<T>,
}
