use defy::defy;
use gloo::storage::Storage;
use i18n::I18n;
use yew::prelude::*;
use yew::suspense::{use_future, UseFutureHandle};
use yew_router::prelude::*;

mod api;
mod comps;
mod i18n;
mod nav;
mod pages;
mod util;

#[function_component]
pub fn App() -> Html {
    let user_host_state = use_state(|| None::<util::RcStr>);
    let user_host = (&*user_host_state).clone();

    let set_user_host = Callback::from(move |host: util::RcStr| {
        if let Err(err) = gloo::storage::LocalStorage::set(api::LOCAL_STORAGE_KEY, host.to_string()) {
            log::error!("store host: {err:?}");
        }
        user_host_state.set(Some(host));
    });

    log::debug!("user_host = {user_host:?}");

    defy! {
        Suspense(fallback = fallback()) {
            Main(user_host = user_host, set_user_host = set_user_host);
        }
    }
}

#[function_component]
fn Main(props: &MainProps) -> HtmlResult {
    let force_update_trigger = use_force_update();
    let set_user_host = props.set_user_host.reform(move |x| {
        force_update_trigger.force_update();
        x
    });

    let api = api::use_client(props.user_host.clone());
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
                aside(class = "column is-narrow is-fullheight section menu"){
                    nav::Comp(
                        i18n = i18n.clone(),
                        api = api.clone(),
                        discovery = discovery.clone(),
                        set_user_host = set_user_host,
                    );
                }
                div(class="container column"){
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

#[derive(Clone, PartialEq, Properties)]
struct MainProps {
    user_host: Option<util::RcStr>,
    set_user_host: Callback<util::RcStr>,
}

fn fallback() -> Html {
    defy! {
        section(class = "hero is-fullheight") {
            div(class = "hero-body") {
                p(class = "title has-text") {
                    + "Connecting to server\u{2026}";
                }
            }
        }
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
