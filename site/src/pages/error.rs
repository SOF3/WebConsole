use defy::defy;
use yew::prelude::*;

use crate::comps;
use crate::i18n::I18n;
use crate::util::RcStr;

#[function_component]
pub fn Error(props: &Props) -> Html {
    defy! {
        div(class = "section") {
            div(class = "container") {
                article(class = "message is-danger") {
                    div(class = "message-header") {
                        p {
                            + props.i18n.as_ref().map_or_else(
                                || String::from("Error"),
                                |i18n| i18n.disp("base-error"),
                            );
                        }
                    }
                    div(class = "message-body") {
                        pre {
                            + &props.err;
                        }
                    }
                }
            }
        }
        if let Some((host, callback)) = &props.set_user_host {
            div(class = "section") {
                div(class = "container") {
                    comps::TextButton(
                        default_value = Some(host.to_istring()),
                        button = props.i18n.as_ref().map_or_else(
                            || String::from("Switch server"),
                            |i18n| i18n.disp("base-nav-switch-server"),
                        ),
                        callback = callback.reform(Into::into),
                    );
                }
            }

            div(class = "section") {
                div(class = "container") {
                    div(class = "content") {
                        p {
                            + "You need to install the WebConsole plugin on your server to access it through this website.";
                        }

                        a(href = "https://sof3.github.io/WebConsole/WebConsole.phar") {
                            button(class = "button is-medium is-primary") {
                                span(class = "icon mdi mdi-download");
                                span { + "Download plugin"; }
                            }
                        }
                    }
                }
            }

        }
    }
}

#[derive(PartialEq, Properties)]
pub struct Props {
    pub i18n:          Option<I18n>,
    pub err:           String,
    pub set_user_host: Option<(RcStr, Callback<RcStr>)>,
}
