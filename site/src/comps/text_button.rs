use defy::defy;
use yew::prelude::*;

#[function_component]
pub fn TextButton(props: &Props) -> Html {
    let input_node = use_node_ref();
    let callback = {
        let callback = props.callback.clone();
        let input_node = input_node.clone();
        move || {
            let input = input_node.cast::<web_sys::HtmlInputElement>().unwrap();
            callback.emit(input.value());
        }
    };

    defy! {
        input(
            ref = input_node.clone(),
            class = "input",
            type = "text",
            value = props.default_value.clone(),
            placeholder = props.placeholder.clone(),
            onkeydown = Callback::from({
                let callback = callback.clone();
                move |event: web_sys::KeyboardEvent| {
                    if event.key() == "Enter" {
                        callback();
                    }
                }
            }),
        );
        button(
            class = "button is-link",
            onclick = Callback::from(move |_| {
                callback();
            }),
        ) {
            + props.button.clone();
        }
    }
}

#[derive(PartialEq, Properties)]
pub struct Props {
    #[prop_or_default]
    pub placeholder:   Option<AttrValue>,
    #[prop_or_default]
    pub default_value: Option<AttrValue>,
    #[prop_or_default]
    pub focused:       bool,
    pub button:        AttrValue,
    pub callback:      Callback<String>,
}
