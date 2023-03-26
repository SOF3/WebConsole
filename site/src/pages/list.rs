use std::collections::{BTreeMap, HashSet};

use anyhow::Context as _;
use defy::defy;
use futures::StreamExt;
use wasm_bindgen_futures::spawn_local;
use yew::prelude::*;

use crate::i18n::I18n;
use crate::util::{self, Grc, RcStr};
use crate::{api, comps};

#[function_component]
pub fn Comp(props: &Props) -> Html {
    let Some(api) = props
        .discovery
        .apis
        .get(&api::GroupKindRef { group: props.group.as_str(), kind: props.kind.as_str() }
            as &dyn api::GroupKindDyn)
    else {
        return defy! { + "Error: unknown API"; }
    };

    let list_display_name = props.i18n.disp(&api.display_name);
    use_effect_with_deps(
        {
            let list_display_name = format!("{list_display_name}");
            move |_| {
                gloo::utils::document().set_title(&list_display_name);
            }
        },
        (props.group.clone(), props.kind.clone()),
    );

    let hidden = use_state_eq(HashSet::new);
    let hidden = &*hidden;

    let Some(def) =
        props
            .discovery
            .apis
            .get(&api::GroupKindRef { group: &props.group, kind: &props.kind }
                as &dyn api::GroupKindDyn)
        else {
            return defy! { +"no such type"; }
        };

    defy! {
        h1(class = "title") {
            + props.i18n.disp(&api.display_name);
        }

        div(class = "columns") {
            div(class = "column is-narrow") {
                FieldSelector(
                    i18n = props.i18n.clone(),
                    def = def.clone(),
                    hidden = hidden.clone(),
                );
            }

            div(class = "column") {
                Suspense(fallback = fallback()) {
                    List(
                        api = props.api.clone(),
                        i18n = props.i18n.clone(),
                        group = props.group.clone(),
                        kind = props.kind.clone(),
                        def = def.clone(),
                        hidden = hidden.clone(),
                    );
                }
            }
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
pub struct Props {
    pub api:       Grc<api::Client>,
    pub i18n:      I18n,
    pub discovery: Grc<api::Discovery>,
    pub group:     AttrValue,
    pub kind:      AttrValue,
}

fn fallback() -> Html {
    defy! {
        + "Loading";
    }
}

#[function_component]
fn FieldSelector(props: &FieldSelectorProps) -> Html {
    defy! {
        nav(class = "panel") {
            p(class = "panel-heading") {
                + props.i18n.disp("base-properties-title");
            }

            div(class = "panel-block") {
                input(class = "input", placeholder = props.i18n.disp("base-properties-search"));
            }

            for field in props.def.fields.values() {
                a(class = "panel-block") {
                    span(class = "panel-icon") {
                        input(type = "checkbox", checked = true);
                    }
                    + props.i18n.disp(&field.display_name);
                }
            }
        }
    }
}

#[derive(Clone, PartialEq, Properties)]
struct FieldSelectorProps {
    i18n:   I18n,
    def:    api::Desc,
    hidden: HashSet<RcStr>,
}

struct List {
    objects: BTreeMap<String, api::Object>,
    err:     Option<String>,
}

impl Component for List {
    type Message = ListMsg;
    type Properties = ListProps;

    fn create(ctx: &Context<Self>) -> Self {
        let props = ctx.props();

        let callback = ctx.link().callback(|event| ListMsg::Event(event));
        spawn_local({
            let api = props.api.clone();
            let group = props.group.to_string();
            let kind = props.kind.to_string();
            async move {
                match async {
                    let mut stream = api.watch(group, kind).await.context("watch for objects")?;

                    while let Some(event) = stream.next().await {
                        callback.emit(event);
                    }

                    Ok(())
                }
                .await
                {
                    Ok(()) => (),
                    Err(err) => callback.emit(Err(err)),
                }
            }
        });

        Self { objects: BTreeMap::new(), err: None }
    }

    fn update(&mut self, _ctx: &Context<Self>, msg: Self::Message) -> bool {
        match msg {
            ListMsg::Event(Ok(event)) => {
                match event {
                    api::ObjectEvent::Added { item: object } => {
                        self.objects.insert(object.name.clone(), object);
                    }
                    api::ObjectEvent::Removed { name } => {
                        self.objects.remove(&*name);
                    }
                    api::ObjectEvent::FieldUpdate { name, field, value } => {
                        let Some(object) = self.objects.get_mut(&*name) else { return false };
                        if let Err(err) = util::set_json_path(&mut object.fields, &*field, value) {
                            log::warn!("invalid json path: {err:?}");
                        }
                    }
                }
                true
            }
            ListMsg::Event(Err(err)) => {
                self.err = Some(format!("{err:?}"));
                true
            }
        }
    }

    fn view(&self, ctx: &Context<Self>) -> Html {
        let hidden = &ctx.props().hidden;
        let fields: Vec<_> = ctx
            .props()
            .def
            .fields
            .values()
            .filter(|&field| !hidden.contains(&field.path))
            .collect();
        let i18n = &ctx.props().i18n;

        defy! {
            if let Some(err) = &self.err {
                article(class = "message is-danger") {
                    div(class = "message-header") {
                        p {
                            + "Error";
                        }
                    }
                    div(class = "message-body") {
                        pre {
                            + err;
                        }
                    }
                }
            }

            div {
                for object in self.objects.values() {
                    div(class = "card object-thumbnail") {
                        header(class = "card-header") {
                            p(class = "card-header-title") {
                                + &object.name;
                            }
                        }

                        if !fields.is_empty() {
                            div(class = "card-content") {
                                div(class = "content") {
                                    for &field in &fields {
                                        if let Some(value) = util::get_json_path(&object.fields, &field.path) {
                                            span(class = "tag") {
                                                + i18n.disp(&field.display_name);
                                            }

                                            comps::InlineDisplay(
                                                i18n = i18n.clone(),
                                                value = value.clone(),
                                                ty = field.ty.clone(),
                                            );
                                            + " ";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

enum ListMsg {
    Event(anyhow::Result<api::ObjectEvent>),
}

#[derive(Clone, PartialEq, Properties)]
struct ListProps {
    api:    Grc<api::Client>,
    i18n:   I18n,
    group:  AttrValue,
    kind:   AttrValue,
    def:    api::Desc,
    hidden: HashSet<RcStr>,
}
