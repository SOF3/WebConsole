use std::collections::HashSet;

use defy::defy;
use yew::prelude::*;

use crate::api;
use crate::i18n::I18n;
use crate::util::{Grc, RcStr};

mod field_selector;
mod object_list;
mod panel_block;

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

    let list_display_name = props.i18n.disp(&api.display_name);
    use_effect_with_deps(
        {
            let list_display_name = format!("{list_display_name}");
            move |_| {
                gloo::utils::document().set_title(&list_display_name);
            }
        },
        (props.group.clone(), props.kind.clone()),
    );

    let Some(def) =
        props
            .discovery
            .apis
            .get(&api::GroupKindRef { group: &props.group, kind: &props.kind }
                as &dyn api::GroupKindDyn)
        else {
            return defy! { +"no such type"; }
        };

    #[derive(Clone, Default)]
    struct HiddenState {
        hidden: HashSet<RcStr>,
        dep:    Option<api::Desc>,
    }

    impl HiddenState {
        fn of(def: &api::Desc) -> Self {
            let mut hidden = HashSet::new();
            for field in &def.fields {
                if field.metadata.hide_by_default {
                    hidden.insert(field.path.clone());
                }
            }

            Self { hidden, dep: Some(def.clone()) }
        }
    }

    let hidden_state = use_state(HiddenState::default);

    if hidden_state.dep.as_ref() != Some(def) {
        hidden_state.set(HiddenState::of(def));
    }

    let hidden = &*hidden_state;

    defy! {
        h1(class = "title") {
            + props.i18n.disp(&api.display_name);
        }

        div(class = "columns") {
            div(class = "column is-narrow") {
                field_selector::FieldSelector(
                    i18n = props.i18n.clone(),
                    def = def.clone(),
                    hidden = hidden.hidden.clone(),
                    set_visible_callback = Callback::from({
                        let hidden_state = hidden_state.clone();

                        move |(field_path, visible)| {
                            let mut hidden: HiddenState = (&*hidden_state).clone();

                            if visible {
                                hidden.hidden.remove(&field_path);
                            } else {
                                hidden.hidden.insert(field_path);
                            }

                            hidden_state.set(hidden);
                        }
                    })
                );
            }

            div(class = "column") {
                Suspense(fallback = fallback()) {
                    object_list::ObjectList(
                        api = props.api.clone(),
                        i18n = props.i18n.clone(),
                        group = props.group.clone(),
                        kind = props.kind.clone(),
                        def = def.clone(),
                        hidden = hidden.hidden.clone(),
                    );
                }
            }
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
}

fn fallback() -> Html {
    defy! {
        + "Loading";
    }
}
