use std::borrow::Borrow;
use std::collections::{hash_map, HashMap};
use std::convert::Infallible;
use std::hash::Hash;
use std::marker::PhantomData;
use std::rc::Rc;
use std::str::FromStr;
use std::{fmt, ops};

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
#[derive(PartialEq, Eq)]
pub struct IdMap<K: Eq + Hash, V> {
    map: HashMap<K, V>,
}

impl<K: Eq + Hash, V> IdMap<K, V> {
    pub fn values(&self) -> impl Iterator<Item = &V> { self.map.values() }

    pub fn get<Q: Hash + Eq + ?Sized>(&self, key: &Q) -> Option<&V>
    where
        K: Borrow<Q>,
    {
        self.map.get(key)
    }
}

impl<K: Eq + Hash, V> Default for IdMap<K, V> {
    fn default() -> Self { Self { map: HashMap::new() } }
}

impl<'t, K: Eq + Hash, V> IntoIterator for &'t IdMap<K, V> {
    type Item = &'t V;
    type IntoIter = hash_map::Values<'t, K, V>;

    fn into_iter(self) -> Self::IntoIter { self.map.values() }
}

impl<'de, K, V> Deserialize<'de> for IdMap<K, V>
where
    K: Eq + Hash,
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
            K: Eq + Hash,
            V: HasId<K> + Deserialize<'de>,
        {
            type Value = HashMap<K, V>;

            fn expecting(&self, formatter: &mut fmt::Formatter) -> fmt::Result {
                formatter.write_str("a sequence")
            }

            fn visit_seq<A>(self, mut seq: A) -> Result<Self::Value, A::Error>
            where
                A: SeqAccess<'de>,
            {
                let mut map = HashMap::new();

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
