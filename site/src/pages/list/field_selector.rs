use std::cmp;
use std::collections::HashSet;

use defy::defy;
use yew::prelude::*;

use super::panel_block;
use crate::api;
use crate::i18n::I18n;
use crate::util::RcStr;

#[function_component]
pub fn FieldSelector(props: &FieldSelectorProps) -> Html {
    let set_visible_callback = props.set_visible_callback.clone();

    let filter_node_ref = use_node_ref();
    let filter_pattern_state = use_state_eq(String::new);
    let filter_pattern = &*filter_pattern_state;

    let on_filter_change = {
        let filter_pattern_state = filter_pattern_state.clone();
        let filter_node_ref = filter_node_ref.clone();
        move || {
            let input = filter_node_ref.cast::<web_sys::HtmlInputElement>().unwrap();
            filter_pattern_state.set(input.value());
        }
    };

    defy! {
        nav(class = "panel") {
            p(class = "panel-heading") {
                + props.i18n.disp("base-properties-title");
            }

            div(class = "panel-block") {
                input(
                    ref = filter_node_ref.clone(),
                    class = "input",
                    placeholder = props.i18n.disp("base-properties-search"),
                    onchange = Callback::from({
                        let on_filter_change = on_filter_change.clone();
                        move |_| on_filter_change()
                    }),
                    onkeyup = Callback::from(move |_| on_filter_change()),
                );
            }

            if true {
                let fields = {
                    let mut fields: Vec<_> = props.def.fields.values().collect();
                    fields.sort_by_key(|field| (cmp::Reverse(field.metadata.display_priority), &field.path));
                    fields.into_iter()
                };

                for field in fields {
                    let display_name = props.i18n.disp(&field.display_name);
                    if filter_pattern.is_empty() || display_name.contains(filter_pattern) || field.path.contains(filter_pattern) {
                        panel_block::PanelBlock(
                            text = display_name,
                            checked = !props.hidden.contains(&field.path),
                            callback = Callback::from({
                                let field_path = field.path.clone();
                                let set_visible_callback = set_visible_callback.clone();
                                move |checked: bool| {
                                    set_visible_callback.emit((field_path.clone(), checked));
                                }
                            }),
                        );
                    }
                }
            }
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct FieldSelectorProps {
    pub i18n:                 I18n,
    pub def:                  api::Desc,
    pub hidden:               HashSet<RcStr>,
    pub set_visible_callback: Callback<(RcStr, bool)>,
}
