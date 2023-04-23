use defy::defy;
use gloo::storage::Storage;
use i18n::I18n;
use util::{Grc, RcStr};
use yew::prelude::*;
use yew::suspense::{use_future_with_deps, UseFutureHandle};
use yew_router::prelude::*;

mod api;
mod comps;
mod i18n;
mod nav;
mod pages;
mod util;

#[function_component]
pub fn App() -> Html {
    let user_host_state = use_state(|| api::infer_host());
    let user_host = (&*user_host_state).clone();

    let set_user_host = Callback::from(move |host: RcStr| {
        if let Err(err) = gloo::storage::LocalStorage::set(api::LOCAL_STORAGE_KEY, host.to_string())
        {
            log::error!("store host: {err:?}");
        }
        user_host_state.set(host);
    });

    log::debug!("user_host = {user_host:?}");

    defy! {
        Suspense(fallback = fallback(user_host.clone(), set_user_host.clone())) {
            Main(host = user_host, set_user_host = set_user_host);
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

    let api = api::use_client(props.host.clone());
    let queries: UseFutureHandle<anyhow::Result<_>> = use_future_with_deps(
        |_| {
            let api = api.clone();
            async move {
                let (locales, discovery) = futures::join!(api.locales(), api.discovery());
                Ok((locales?, discovery?))
            }
        },
        api.host.clone(),
    )?;
    let (i18n, discovery) = match &*queries {
        Ok(data) => data.clone(),
        Err(err) => {
            return Ok(defy! {
                pages::error::Error(
                    err = format!("{err:?}"),
                    set_user_host = Some((api.host.clone(), set_user_host)),
                );
            })
        }
    };

    Ok(defy! {
        section(class="main-content columns is-fullheight") {
            BrowserRouter {
                aside(class = "column is-narrow is-fullheight section menu main-sidebar"){
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
    host:          RcStr,
    set_user_host: Callback<RcStr>,
}

fn fallback(host: RcStr, set_user_host: Callback<RcStr>) -> Html {
    defy! {
        section(class = "hero is-fullheight") {
            div(class = "hero-body") {
                p(class = "title has-text") {
                    + "Connecting to server\u{2026}";
                }

                div(class = "section") {
                    div(class = "container") {
                        comps::TextButton(
                            default_value = Some(host.to_istring()),
                            button = "Switch server",
                            callback = set_user_host.reform(Into::into),
                        );
                    }
                }
            }
        }
    }
}

#[derive(Routable, Clone, PartialEq)]
enum Route {
    #[at("/:group/:kind")]
    List { group: RcStr, kind: RcStr },
    #[at("/:group/:kind/:name")]
    Info { group: RcStr, kind: RcStr, name: RcStr },
    #[at("/")]
    Home,
}

fn switch(route: Route, api: Grc<api::Client>, i18n: I18n, discovery: Grc<api::Discovery>) -> Html {
    defy! {
        match route {
            Route::List { group, kind } => {
                pages::list::Comp(api, i18n, discovery, group, kind);
            }
            Route::Info { group, kind, name } => {
                pages::info::Comp(api, i18n, discovery, group, kind, name);
            }
            Route::Home => {
                pages::home::Comp(i18n);
            }
        }
    }
}
