use std::collections::HashSet;
use std::future::Future;
use std::rc::Rc;

use anyhow::Context;
use fluent::{FluentBundle, FluentResource};
use gloo::net::http;
use serde::de::DeserializeOwned;
use serde::Deserialize;
use yew::hook;

use crate::i18n::{self, I18n};
use crate::util::{self, Grc, RcStr};

#[hook]
pub fn use_client() -> Grc<ApiClient> {
    Grc::new(ApiClient {
        host: "http://localhost:5050".to_string(), // TODO
    })
}

pub struct ApiClient {
    pub host: String,
}

impl ApiClient {
    pub fn locales(self: &Rc<Self>) -> impl Future<Output = anyhow::Result<I18n>> {
        let this = self.clone();
        async move { this.locales_impl().await }
    }
    async fn locales_impl(&self) -> anyhow::Result<I18n> {
        let locales: HashSet<Box<str>> =
            self.request("locales").await.context("request locales")?;
        let prefers = gloo::utils::window().navigator().languages();
        let prefer = prefers.find(&mut |value, _i, _array| {
            let value = value.as_string().expect("locales should be list of strings");
            locales.contains(value.as_str())
        });
        let prefer = match prefer.as_string() {
            Some(prefer) => prefer,
            None => prefers.get(0).as_string().expect("locales list is empty"),
        };

        let resp = http::Request::new(&format!("{}/{prefer}.ftl", &self.host))
            .send()
            .await
            .context("request ftl file")?;
        let ftl_str = resp.text().await.context("receive ftl file")?;
        let res = match FluentResource::try_new(ftl_str) {
            Ok(res) => res,
            Err((_res, errs)) => {
                let err = errs.into_iter().next().expect("at least one error");
                return Err(err).context("parse fluent resource");
            }
        };

        let mut bundle =
            FluentBundle::new(vec![prefer.parse().context("server provided invalid locale")?]);
        if let Err(errs) = bundle.add_resource(res) {
            let err = errs.into_iter().next().expect("at least one error");
            return Err(err).context("add resource to fluent bundle");
        }

        Ok(I18n { bundle: Grc::new(bundle) })
    }

    pub fn discovery(self: &Rc<Self>) -> impl Future<Output = anyhow::Result<Grc<Discovery>>> {
        let this = self.clone();
        async move { this.discovery_impl().await }
    }
    async fn discovery_impl(&self) -> anyhow::Result<Grc<Discovery>> {
        Ok(Grc::new(self.request("discovery").await?))
    }

    async fn request<T: DeserializeOwned>(&self, path: &str) -> anyhow::Result<T> {
        Ok(http::Request::new(&format!("{}/{path}", &self.host)).send().await?.json::<T>().await?)
    }
}

#[derive(Deserialize, PartialEq, Eq)]
pub struct Discovery {
    pub groups: util::IdMap<ApiGroup>,
    pub apis:   util::IdMap<ApiDesc>,
}

macro_rules! impl_has_id {
    ($ty:ty) => {
        impl util::HasId for $ty {
            fn id(&self) -> &str { &self.id }
        }
    };
}

#[derive(Deserialize, PartialEq, Eq)]
pub struct ApiDesc {
    pub id:           RcStr,
    pub display_name: i18n::Key,
    pub group:        RcStr,
}
impl_has_id!(ApiDesc);

#[derive(Deserialize, PartialEq, Eq)]
pub struct ApiGroup {
    pub id:               RcStr,
    pub display_name:     i18n::Key,
    pub display_priority: u32,
}
impl_has_id!(ApiGroup);
