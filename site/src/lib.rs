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
    let client_config_state = use_state(|| api::infer_host());
    let client_config = client_config_state.clone();

    let set_client_config = Callback::from(move |config: api::ClientConfig| {
        if let Err(err) = gloo::storage::LocalStorage::set(api::LOCAL_STORAGE_KEY, &config) {
            log::error!("store host: {err:?}");
        }
        client_config_state.set(config);
    });

    defy! {
        Suspense(fallback = fallback(client_config.host.clone(), set_client_config.clone())) {
            Main(config = client_config, set_client_config = set_client_config);
        }
    }
}

#[function_component]
fn Main(props: &MainProps) -> HtmlResult {
    let force_update_trigger = use_force_update();
    let set_client_config = props.set_client_config.reform(move |x| {
        force_update_trigger.force_update();
        x
    });

    let api = api::use_client(props.host.clone(), props.passphrase.clone());
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
                    set_client_config = Some((api.host.clone(), set_client_config)),
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
                        set_client_config = set_client_config,
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
    config:          api::ClientConfig,
    set_client_config: Callback<RcStr>,
}

fn fallback(host: RcStr, set_client_config: Callback<api::ClientConfig>) -> Html {
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
                            callback = set_client_config.reform(Into::into),
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
    #[at("/")]
    Home,
}

fn switch(route: Route, api: Grc<api::Client>, i18n: I18n, discovery: Grc<api::Discovery>) -> Html {
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
