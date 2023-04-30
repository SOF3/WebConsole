use std::pin::Pin;
use std::rc::Rc;

use anyhow::{Context as _, Result};
use defy::defy;
use futures::channel::oneshot;
use futures::stream::FusedStream;
use futures::{Future, StreamExt};
use wasm_bindgen_futures::spawn_local;
use yew::prelude::*;

pub type NeedRender = bool;

pub trait State: 'static {
    type Input: PartialEq + Clone;
    type Event;

    fn new(input: &Self::Input) -> Self;

    fn reset(&mut self);

    fn watch(
        &self,
        input: &Self::Input,
    ) -> Pin<
        Box<dyn Future<Output = Result<Box<dyn FusedStream<Item = Result<Self::Event>> + Unpin>>>>,
    >;

    fn update(&mut self, msg: Self::Event) -> NeedRender;

    type Deps<'t>: PartialEq + 't;
    fn deps<'t>(input: &'t Self::Input) -> Self::Deps<'t>;
}

pub struct Comp<T: State> {
    state:       T,
    err:         Option<String>,
    shutdown_tx: Option<oneshot::Sender<()>>,
}

fn start_watch<T: State>(ctx: &Context<Comp<T>>, comp: &mut Comp<T>) {
    let (shutdown_tx, mut shutdown_rx) = oneshot::channel::<()>();

    let callback = ctx.link().callback(|event| Msg::Event(event));

    let watch = comp.state.watch(&ctx.props().input);
    spawn_local({
        async move {
            match async {
                let mut stream = watch.await.context("open watch stream")?;

                loop {
                    let event: anyhow::Result<T::Event> = futures::select! {
                        event = stream.next() => match event {
                            Some(event) => event,
                            None => return Ok(()),
                        },
                        _ = shutdown_rx => return Ok(()),
                    };
                    callback.emit(event);
                }
            }
            .await
            {
                Ok(()) => (),
                Err(err) => callback.emit(Err(err)),
            }
        }
    });

    if let Some(old) = comp.shutdown_tx.replace(shutdown_tx) {
        _ = old.send(());
    }

    comp.state.reset();
}

impl<T: State> Component for Comp<T> {
    type Message = Msg<T>;
    type Properties = Props<T>;

    fn create(ctx: &Context<Self>) -> Self {
        let mut obj =
            Self { state: T::new(&ctx.props().input), err: None, shutdown_tx: None };
        start_watch(ctx, &mut obj);
        obj
    }

    fn destroy(&mut self, _ctx: &Context<Self>) {
        if let Some(shutdown_tx) = self.shutdown_tx.take() {
            _ = shutdown_tx.send(());
        }
    }

    fn update(&mut self, _ctx: &Context<Self>, msg: Self::Message) -> bool {
        match msg {
            Msg::Event(Ok(event)) => self.state.update(event),
            Msg::Event(Err(err)) => {
                self.err = Some(format!("{err:?}"));
                true
            }
        }
    }

    fn changed(&mut self, ctx: &Context<Self>, old_props: &Self::Properties) -> bool {
        if T::deps(&old_props.input) != T::deps(&ctx.props().input) {
            start_watch(ctx, self);
        }

        true
    }

    fn view(&self, ctx: &Context<Self>) -> Html {
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

            + (ctx.props().body)(&self.state);
        }
    }
}

pub enum Msg<T: State> {
    Event(anyhow::Result<T::Event>),
}

#[derive(Clone, Properties)]
pub struct Props<T: State> {
    pub input: T::Input,
    pub body:  Rc<dyn Fn(&T) -> Html>,
}

impl<T: State> PartialEq for Props<T> {
    fn eq(&self, other: &Self) -> bool {
        self.input == other.input && Rc::ptr_eq(&self.body, &other.body)
    }
}
