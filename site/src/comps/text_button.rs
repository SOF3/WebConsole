use defy::defy;
use yew::prelude::*;

#[function_component]
pub fn TextButton(props: &Props) -> Html {
    let input_node = use_node_ref();

    defy! {
        div(class = "level") {
            div(class = "level-item") {
                input(
                    ref = input_node.clone(),
                    class = "input",
                    type = "text",
                    value = props.default_value.clone(),
                    placeholder = props.placeholder.clone(),
                    );
                button(
                    class = "button is-link",
                    onclick = props.callback.reform(move |_| {
                        let input = input_node.cast::<web_sys::HtmlInputElement>().unwrap();
                        input.value()
                    }),
                    ) {
                    + props.button.clone();
                }
            }
        }
    }
}

#[derive(PartialEq, Properties)]
pub struct Props {
    #[prop_or_default]
    pub placeholder:   Option<AttrValue>,
    #[prop_or_default]
    pub default_value: Option<AttrValue>,
    pub button:        AttrValue,
    pub callback:      Callback<String>,
}
