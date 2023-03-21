use defy::defy;
use indexmap::IndexMap;
use yew::prelude::*;
use yew_router::prelude::*;

use crate::i18n::I18n;
use crate::{api, util, Route};

#[function_component]
pub fn Comp(props: &Props) -> HtmlResult {
    struct GroupApis<'t> {
        group: &'t api::Group,
        apis:  Vec<&'t api::Desc>,
    }

    let mut groups: IndexMap<_, GroupApis> = props
        .discovery
        .groups
        .values()
        .map(|group| (&group.id, GroupApis { group, apis: Vec::new() }))
        .collect();
    groups.sort_by(|_, group1, _, group2| {
        group1.group.display_priority.cmp(&group2.group.display_priority)
    });

    for api in props.discovery.apis.values() {
        if let Some(group) = groups.get_mut(&api.id.group) {
            group.apis.push(api);
        }
    }

    let choose_server_input = use_node_ref();

    Ok(defy! {
        div(class = "level") {
            div(class = "level-left") {
                div(class = "level-item") {
                    input(
                        ref = choose_server_input.clone(),
                        class = "input",
                        type = "text",
                        value = props.api.host.to_string(),
                    );
                }
            }
            div(class = "level-right") {
                div(class = "level-item") {
                    button(class = "button", onclick = props.set_user_host.reform(move |_| {
                        let input = choose_server_input.cast::<web_sys::HtmlInputElement>().unwrap();
                        util::RcStr::new(input.value())
                    })) {
                        + "Switch server";
                    }
                }
            }
        }
        ul(class = "menu-list") {
            li {
                Link<Route>(to = Route::Home) {
                    + props.i18n.disp("base-home");
                }
            }
        }
        for GroupApis{group, apis} in groups.values() {
            p(class = "menu-label") {
                + props.i18n.disp(&group.display_name);
            }
            ul(class = "menu-list") {
                for api in apis {
                    li {
                        Link<Route>(to = Route::List{
                            group: api.id.group.clone(),
                            kind: api.id.kind.clone(),
                        }) {
                            + props.i18n.disp(&api.display_name);
                        }
                    }
                }
            }
        }
    })
}

#[derive(PartialEq, Properties)]
pub struct Props {
    pub i18n:      I18n,
    pub api: util::Grc<api::Client>,
    pub discovery: util::Grc<api::Discovery>,
    pub set_user_host: Callback<util::RcStr>,
}
