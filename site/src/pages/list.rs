use std::collections::HashSet;

use defy::defy;
use serde::Deserialize;
use yew::prelude::*;

use crate::api;
use crate::comps::SelectButtons;
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

    let display_state = use_state(DisplayState::default);

    if display_state.dep.as_ref() != Some(def) {
        display_state.set(DisplayState::of(def));
    }

    let display = &*display_state;

    defy! {
        h1(class = "title") {
            + props.i18n.disp(&api.display_name);
        }

        div(class = "columns") {
            div(class = "column is-narrow") {
                field_selector::FieldSelector(
                    i18n = props.i18n.clone(),
                    def = def.clone(),
                    display = display.clone(),
                    set_display_mode_callback = Callback::from({
                        let display_state = display_state.clone();
                        move |mode| {
                            let mut display: DisplayState = (&*display_state).clone();
                            display.mode = mode;
                            display_state.set(display);
                        }
                    }),
                    set_visible_callback = Callback::from({
                        let display_state = display_state.clone();

                        move |(field_path, visible)| {
                            let mut display: DisplayState = (&*display_state).clone();

                            if visible {
                                display.hidden.remove(&field_path);
                            } else {
                                display.hidden.insert(field_path);
                            }

                            display_state.set(display);
                        }
                    }),
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
                        hidden = display.hidden.clone(),
                        display_mode = display.mode,
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

#[derive(Clone, Default, PartialEq)]
pub struct DisplayState {
    pub mode:   DisplayMode,
    pub hidden: HashSet<RcStr>,
    pub dep:    Option<api::ObjectDef>,
}

impl DisplayState {
    fn of(def: &api::ObjectDef) -> Self {
        let mut hidden = HashSet::new();
        for field in &def.fields {
            if field.metadata.hide_by_default {
                hidden.insert(field.path.clone());
            }
        }

        Self { mode: def.metadata.default_display_mode, hidden, dep: Some(def.clone()) }
    }
}

#[derive(Clone, Copy, Default, PartialEq, Deserialize)]
#[serde(rename_all = "kebab-case")]
pub enum DisplayMode {
    #[default]
    Cards,
    Table,
}

impl SelectButtons for DisplayMode {
    fn variants() -> &'static [Self] { &[Self::Cards, Self::Table] }

    fn icon(&self) -> &'static str {
        match self {
            Self::Cards => "mdi-view-comfy",
            Self::Table => "mdi-table",
        }
    }

    fn name(&self) -> &'static str {
        match self {
            Self::Cards => "base-display-card",
            Self::Table => "base-display-table",
        }
    }
}
