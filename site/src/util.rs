use std::borrow::Borrow;
use std::collections::{btree_map, BTreeMap};
use std::convert::Infallible;
use std::marker::PhantomData;
use std::rc::Rc;
use std::str::FromStr;
use std::{fmt, ops};

use futures::{Stream, StreamExt};
use pin_project::pin_project;
use serde::de::{SeqAccess, Visitor};
use serde::{Deserialize, Deserializer};
use yew::html::IntoPropValue;
use yew::AttrValue;

/// A global [`Rc`] passed in Properties that is always equal because there is only one instance.
pub struct Grc<T>(Rc<T>);
impl<T> Grc<T> {
    pub fn new(t: T) -> Self { Self(Rc::new(t)) }
}
impl<T> Clone for Grc<T> {
    fn clone(&self) -> Self { Grc(Rc::clone(&self.0)) }
}
impl<T> PartialEq for Grc<T> {
    fn eq(&self, other: &Self) -> bool { Rc::ptr_eq(self, other) }
}
impl<T> ops::Deref for Grc<T> {
    type Target = Rc<T>;
    fn deref(&self) -> &Self::Target { &self.0 }
}

#[derive(Debug, Clone, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub struct RcStr(pub Rc<str>);

impl RcStr {
    pub fn new(s: impl Into<Rc<str>>) -> Self { Self(s.into()) }

    pub fn to_istring(&self) -> AttrValue { AttrValue::Rc(self.0.clone()) }
}

impl From<String> for RcStr {
    fn from(value: String) -> Self { Self(Rc::from(value)) }
}

impl FromStr for RcStr {
    type Err = Infallible;
    fn from_str(s: &str) -> Result<Self, Self::Err> { Ok(Self(s.into())) }
}

impl fmt::Display for RcStr {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result { f.write_str(&self.0) }
}

impl ops::Deref for RcStr {
    type Target = str;

    fn deref(&self) -> &Self::Target { &self.0 }
}

impl From<Rc<str>> for RcStr {
    fn from(value: Rc<str>) -> Self { Self(value) }
}

impl Borrow<str> for RcStr {
    fn borrow(&self) -> &str { &self.0 }
}

impl IntoPropValue<AttrValue> for RcStr {
    fn into_prop_value(self) -> AttrValue { AttrValue::Rc(self.0) }
}

impl<'de> Deserialize<'de> for RcStr {
    fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
    where
        D: Deserializer<'de>,
    {
        Ok(Self(Rc::deserialize(deserializer)?))
    }
}

/// A map that is serialized as a list, indexed with one of its fields.
#[derive(Clone, PartialEq, Eq)]
pub struct IdMap<K: Eq + Ord, V> {
    map: BTreeMap<K, V>,
}

impl<K: Eq + Ord, V> IdMap<K, V> {
    pub fn values(&self) -> impl Iterator<Item = &V> { self.map.values() }

    pub fn get<Q: Eq + Ord + ?Sized>(&self, key: &Q) -> Option<&V>
    where
        K: Borrow<Q>,
    {
        self.map.get(key)
    }
}

impl<K: Eq + Ord, V> Default for IdMap<K, V> {
    fn default() -> Self { Self { map: BTreeMap::new() } }
}

impl<'t, K: Eq + Ord, V> IntoIterator for &'t IdMap<K, V> {
    type Item = &'t V;
    type IntoIter = btree_map::Values<'t, K, V>;

    fn into_iter(self) -> Self::IntoIter { self.map.values() }
}

impl<'de, K, V> Deserialize<'de> for IdMap<K, V>
where
    K: Eq + Ord,
    V: HasId<K> + Deserialize<'de>,
{
    fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
    where
        D: Deserializer<'de>,
    {
        struct ListVisitor<K, V> {
            marker: PhantomData<(K, V)>,
        }

        impl<'de, K, V> Visitor<'de> for ListVisitor<K, V>
        where
            K: Eq + Ord,
            V: HasId<K> + Deserialize<'de>,
        {
            type Value = BTreeMap<K, V>;

            fn expecting(&self, formatter: &mut fmt::Formatter) -> fmt::Result {
                formatter.write_str("a sequence")
            }

            fn visit_seq<A>(self, mut seq: A) -> Result<Self::Value, A::Error>
            where
                A: SeqAccess<'de>,
            {
                let mut map = BTreeMap::new();

                while let Some(value) = seq.next_element::<V>()? {
                    map.insert(value.id(), value);
                }

                Ok(map)
            }
        }

        let visitor = ListVisitor { marker: PhantomData };
        let map = deserializer.deserialize_seq(visitor)?;
        Ok(Self { map })
    }
}

pub trait HasId<Id> {
    fn id(&self) -> Id;
}

#[pin_project]
pub struct StreamWith<S, T> {
    #[pin]
    pub stream: S,
    pub attach: T,
}

impl<S: Stream + Unpin, T> Stream for StreamWith<S, T> {
    type Item = S::Item;

    fn poll_next(
        self: std::pin::Pin<&mut Self>,
        cx: &mut std::task::Context<'_>,
    ) -> std::task::Poll<Option<Self::Item>> {
        self.project().stream.poll_next_unpin(cx)
    }
}

pub fn get_json_path<'t>(
    mut value: &'t serde_json::Value,
    path: &str,
) -> Option<&'t serde_json::Value> {
    for part in path.split(".") {
        if !part.is_empty() {
            let serde_json::Value::Object(map) = value else { return None };
            value = map.get(part)?;
        }
    }

    Some(value)
}

pub fn set_json_path(
    mut object: &mut serde_json::Value,
    path: &str,
    value: serde_json::Value,
) -> anyhow::Result<()> {
    for part in path.split('.') {
        let serde_json::Value::Object(map) = object else { anyhow::bail!("{path} is not under an object") };
        object = match map.get_mut(part) {
            Some(object) => object,
            None => anyhow::bail!("{part:?} does not exist"),
        };
    }

    *object = value;

    Ok(())
}
