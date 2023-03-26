use defy::defy;
use yew::prelude::*;

#[function_component]
pub fn PanelBlock(props: &PanelBlockProps) -> Html {
    let checked = props.checked;
    let callback = props.callback.reform(move |()| !checked);

    defy! {
        a(class = "panel-block", onclick = callback.clone().reform(|_| ())) {
            span(class = "panel-icon") {
                input(
                    type = "checkbox",
                    checked = props.checked,
                    onchange = callback.clone().reform(|_| ()),
                );
            }
            + props.text.clone();
        }
    }
}

#[derive(PartialEq, Properties)]
pub struct PanelBlockProps {
    pub checked:  bool,
    pub text:     AttrValue,
    pub callback: Callback<bool>,
}
