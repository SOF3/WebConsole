use defy::defy;
use indexmap::IndexMap;
use yew::prelude::*;
use yew_router::prelude::*;

use crate::i18n::I18n;
use crate::{api, util, Route};

#[function_component]
pub fn Comp(props: &Props) -> HtmlResult {
    struct GroupApis<'t> {
        group: &'t api::ApiGroup,
        apis:  Vec<&'t api::ApiDesc>,
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
        if let Some(group) = groups.get_mut(&api.group) {
            group.apis.push(api);
        }
    }

    Ok(defy! {
        ul(class = "menu-list") {
            li {
                Link<Route>(to = Route::Home) {
                    + props.i18n.disp("home");
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
                        Link<Route>(to = Route::List{id:api.id.clone()}) {
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
    pub discovery: util::Grc<api::Discovery>,
}
