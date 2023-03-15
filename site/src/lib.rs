use defy::defy;
use i18n::I18n;
use yew::prelude::*;
use yew::suspense::{use_future, UseFutureHandle};
use yew_router::prelude::*;

mod api;
mod i18n;
mod nav;
mod pages;
mod util;

#[function_component]
pub fn App() -> Html {
    defy! {
        Suspense(fallback = fallback()) {
            AppSuspense;
        }
    }
}

#[function_component]
fn AppSuspense() -> HtmlResult {
    let api = api::use_client();
    let queries: UseFutureHandle<anyhow::Result<_>> = use_future(|| {
        let api = api.clone();
        async move {
            let (locales, discovery) = futures::join!(api.locales(), api.discovery());
            Ok((locales?, discovery?))
        }
    })?;
    let (i18n, discovery) = match &*queries {
        Ok(data) => data.clone(),
        Err(err) => return Ok(html! { <pre>{ format!("Error: {err:?}") }</pre> }),
    };

    Ok(defy! {
        section(class="main-content columns is-fullheight") {
            BrowserRouter {
                aside(class = "column is-2 is-fullheight section menu"){
                    nav::Comp(i18n = i18n.clone(), discovery = discovery.clone());
                }
                div(class="container column is-10"){
                    div(class="section"){
                        Switch<Route>(render = {
                            let discovery = discovery.clone();
                            move |route| switch(route, api.clone(), i18n.clone(), discovery.clone())
                        });
                    }
                }
            }
        }
    })
}

fn fallback() -> Html {
    html! {
        <div>{ "Loading API types..." }</div>
    }
}

#[derive(Routable, Clone, PartialEq)]
enum Route {
    #[at("/:group/:kind")]
    List { group: util::RcStr, kind: util::RcStr },
    #[at("/")]
    Home,
}

fn switch(
    route: Route,
    api: util::Grc<api::Client>,
    i18n: I18n,
    discovery: util::Grc<api::Discovery>,
) -> Html {
    defy! {
        match route {
            Route::List { group, kind } => {
                pages::list::Comp(api, i18n, discovery, group, kind);
            }
            Route::Home => {
                pages::home::Comp(i18n);
            }
        }
    }
}
