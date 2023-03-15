use defy::defy;
use yew::prelude::*;
use yew::suspense::use_future_with_deps;

use crate::i18n::I18n;
use crate::util::Grc;
use crate::{api, util};

#[function_component]
pub fn Comp(props: &Props) -> Html {
    let api = match props
        .discovery
        .apis
        .get(&api::GroupKindRef { group: props.group.as_str(), kind: props.kind.as_str() }
            as &dyn api::GroupKindDyn)
    {
        Some(api) => api,
        None => return defy! { + "Error: unknown API"; },
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

    defy! {
        h1(class = "title") {
            + props.i18n.disp(&api.display_name);
        }

        Suspense(fallback = fallback()) {
            List = props.clone();
        }
    }
}

#[function_component]
fn List(props: &Props) -> HtmlResult {
    let list = use_future_with_deps(
        {
            let api = props.api.clone();
            let group = props.group.to_string();
            let kind = props.kind.to_string();
            move |_| api.list(group, kind)
        },
        (props.group.clone(), props.kind.clone()),
    )?;

    let list = match &*list {
        Ok(list) => list,
        Err(err) => {
            return Ok(defy! {
                h2 { +"Error fetching object list"; }
                pre { +format_args!("{err:?}"); }
            })
        }
    };

    Ok(defy! {
        for object in list {
            div(class = "box", style = "display: inline-block;") {
                + &object.name;
            }
        }
    })
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub api:       util::Grc<api::Client>,
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
