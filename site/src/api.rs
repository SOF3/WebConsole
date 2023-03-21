use std::borrow::Borrow;
use std::cmp;
use std::collections::HashSet;
use std::future::Future;
use std::hash::Hash;
use std::rc::Rc;

use anyhow::Context;
use fluent::{FluentBundle, FluentResource};
use futures::{Stream, StreamExt};
use gloo::net::eventsource::futures::EventSource;
use gloo::net::http;
use gloo::storage::Storage as _;
use serde::de::DeserializeOwned;
use serde::Deserialize;
use yew::hook;

use crate::i18n::{self, I18n};
use crate::util::{self, Grc, RcStr, StreamWith};

#[derive(Deserialize)]
struct UrlQuery {
    server: RcStr,
}

pub const LOCAL_STORAGE_KEY: &str = "webconsole:apiserver-addr";

#[hook]
pub fn use_client(user_host: Option<RcStr>) -> Grc<Client> {
    let host = (||{
            if let Some(host) = user_host {
                return host;
            }

            if let Ok(search) = gloo::utils::window().location().search() {
                if let Ok(query) = serde_qs::from_str::<UrlQuery>(&search) {
                    return query.server;
                }
            }

            if let Ok(storage) = gloo::storage::LocalStorage::get::<RcStr>(LOCAL_STORAGE_KEY) {
                return storage;
            }

            RcStr::new("http://localhost:14875")
        })();

    Grc::new(Client { host })
}

pub struct Client {
    pub host: RcStr,
}

impl Client {
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
            None => {
                let locale = locales.iter().next().context("server locale list is mepty")?;
                locale.to_string()
            }
        };

        let resp = http::Request::new(&format!("{}/{prefer}.ftl", &self.host))
            .send()
            .await
            .context("request ftl file")?;
        if resp.status() != 200 {}
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

    pub fn list(
        self: &Rc<Self>,
        group: String,
        kind: String,
    ) -> impl Future<Output = anyhow::Result<Vec<Object>>> {
        let this = self.clone();
        async move { this.list_impl(&group, &kind).await }
    }
    async fn list_impl(&self, group: &str, kind: &str) -> anyhow::Result<Vec<Object>> {
        let resp = http::Request::new(&format!("{}/{group}/{kind}", &self.host))
            .send()
            .await
            .context("sending list request")?;
        let lines = resp.text().await.context("response is not valid UTF-8")?;

        let mut objects = Vec::new();
        for line in lines.split("\n") {
            if !line.is_empty() {
                let object = serde_json::from_str(line).context("deserialize list json")?;
                objects.push(object);
            }
        }

        Ok(objects)
    }

    pub fn watch(
        self: &Rc<Self>,
        group: String,
        kind: String,
    ) -> impl Future<Output = anyhow::Result<impl Stream<Item = anyhow::Result<ObjectEvent>>>> {
        let this = self.clone();
        async move { this.watch_impl(&group, &kind).await }
    }
    async fn watch_impl(
        &self,
        group: &str,
        kind: &str,
    ) -> anyhow::Result<impl Stream<Item = anyhow::Result<ObjectEvent>>> {
        let mut es = EventSource::new(&format!(
            "{}/{group}/{kind}?watch=true&withExisting=true",
            &self.host
        ))
        .context("instantiate EventSource to watch events")?;
        let sub = es.subscribe("message").context("subscribe to message events")?;

        let mapped = sub.map(|event| {
            let (_, event) =
                event.map_err(|err| anyhow::anyhow!("{err}")).context("read event error")?;
            let de = serde_json::from_str(
                &event.data().as_string().context("event data should be string")?,
            )
            .context("deserialize event json")?;
            Ok(de)
        });

        Ok(StreamWith { stream: mapped, attach: es })
    }

    async fn request<T: DeserializeOwned>(&self, path: &str) -> anyhow::Result<T> {
        Ok(http::Request::new(&format!("{}/{path}", &self.host)).send().await?.json::<T>().await?)
    }
}

#[derive(Deserialize, PartialEq, Eq)]
pub struct Discovery {
    pub groups: util::IdMap<RcStr, Group>,
    pub apis:   util::IdMap<GroupKind, Desc>,
}

#[derive(Deserialize, Clone, PartialEq, Eq)]
pub struct Desc {
    #[serde(flatten)]
    pub id:           GroupKind,
    pub display_name: i18n::Key,
    pub fields:       util::IdMap<RcStr, FieldDef>,
}
impl util::HasId<GroupKind> for Desc {
    fn id(&self) -> GroupKind { self.id.clone() }
}

#[derive(Deserialize, Clone, PartialEq, Eq)]
pub struct FieldDef {
    pub path:         RcStr,
    pub display_name: i18n::Key,
    #[serde(rename = "type")]
    pub ty:           FieldType,
}
impl util::HasId<RcStr> for FieldDef {
    fn id(&self) -> RcStr { self.path.clone() }
}

#[derive(Deserialize, Clone, PartialEq, Eq)]
#[serde(tag = "type", rename_all = "snake_case")]
pub enum FieldType {
    String {},
    Int64 {},
    Float64 {},
    Bool {},
    Enum {
        options: util::IdMap<RcStr, EnumOption>,
    },
    Object {
        #[serde(flatten)]
        gk: GroupKind,
    },
    Nullable {
        item: Box<FieldType>,
    },
    List {
        item: Box<FieldType>,
    },
    Compound {
        fields: util::IdMap<RcStr, CompoundSubfield>,
    },
}

#[derive(Deserialize, Clone, PartialEq, Eq)]
pub struct CompoundSubfield {
    key:  RcStr,
    name: i18n::Key,
    #[serde(rename = "type")]
    ty:   FieldType,
}

impl util::HasId<RcStr> for CompoundSubfield {
    fn id(&self) -> RcStr { self.key.clone() }
}

#[derive(Deserialize, Clone, PartialEq, Eq)]
pub struct EnumOption {
    pub id:   RcStr,
    pub i18n: i18n::Key,
}

impl util::HasId<RcStr> for EnumOption {
    fn id(&self) -> RcStr { self.id.clone() }
}

#[derive(Deserialize, Clone, PartialEq, Eq)]
pub struct Group {
    pub id:               RcStr,
    pub display_name:     i18n::Key,
    pub display_priority: u32,
}
impl util::HasId<RcStr> for Group {
    fn id(&self) -> RcStr { self.id.clone() }
}

#[derive(Deserialize, Clone, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub struct GroupKind {
    pub group: RcStr,
    pub kind:  RcStr,
}

#[derive(PartialEq, Eq, PartialOrd, Ord, Hash)]
pub struct GroupKindRef<'t> {
    pub group: &'t str,
    pub kind:  &'t str,
}

pub trait GroupKindDyn {
    fn group(&self) -> &str;
    fn kind(&self) -> &str;
}
impl GroupKindDyn for GroupKind {
    fn group(&self) -> &str { return &self.group }
    fn kind(&self) -> &str { return &self.kind }
}
impl<'t> GroupKindDyn for GroupKindRef<'t> {
    fn group(&self) -> &str { return &self.group }
    fn kind(&self) -> &str { return &self.kind }
}

impl Hash for dyn GroupKindDyn + '_ {
    fn hash<H: std::hash::Hasher>(&self, state: &mut H) {
        self.group().hash(state);
        self.kind().hash(state);
    }
}
impl PartialEq for dyn GroupKindDyn + '_ {
    fn eq(&self, other: &Self) -> bool {
        self.group() == other.group() && self.kind() == other.kind()
    }
}
impl Eq for dyn GroupKindDyn + '_ {}
impl PartialOrd for dyn GroupKindDyn + '_ {
    fn partial_cmp(&self, other: &Self) -> Option<cmp::Ordering> { Some(self.cmp(other)) }
}
impl Ord for dyn GroupKindDyn + '_ {
    fn cmp(&self, other: &Self) -> cmp::Ordering {
        self.group().cmp(other.group()).then_with(|| self.kind().cmp(other.kind()))
    }
}

impl<'t> Borrow<dyn GroupKindDyn + 't> for GroupKind {
    fn borrow(&self) -> &(dyn GroupKindDyn + 't) { self }
}

#[derive(Debug, Deserialize)]
pub struct Object {
    #[serde(rename = "_name")]
    pub name:   String,
    #[serde(flatten)]
    pub fields: serde_json::Value,
}

#[derive(Debug, Deserialize)]
#[serde(tag = "event")]
pub enum ObjectEvent {
    Added { object: Object },
    Removed { name: String },
}
