use fluent::{fluent_args, FluentArgs, FluentBundle, FluentResource};

use crate::util::{Grc, RcStr};

pub type Key = RcStr;

#[derive(Clone, PartialEq)]
pub struct I18n {
    pub bundle: Grc<FluentBundle<FluentResource>>,
}

impl I18n {
    pub fn disp(&self, id: &str) -> String { self.disp_with(id, fluent_args![]) }

    pub fn disp_with(&self, id: &str, args: FluentArgs) -> String {
        let pat = match self.bundle.get_message(id).and_then(|message| message.value()) {
            Some(pat) => pat,
            None => return format!("Missing translation: \"{id}\""),
        };

        let mut errors = Vec::new();
        let ret = self.bundle.format_pattern(pat, Some(&args), &mut errors);
        if let Some(err) = errors.into_iter().next() {
            return format!("Formatting error: {err}");
        }

        ret.to_string()
    }
}
