const domLoaded = () => {
    window.Prism = window.Prism || {};
    window.Prism.manual = true;
    const languages = Array.from(
        new Set(
            Array.from(
                document.querySelectorAll(
                    "[data-mw-syntaxhighlight-resourceloader-module]"
                ),
                (elem) => elem.dataset.mwSyntaxhighlightResourceloaderModule
            )
        )
    );
    const plugins = [];
    if (document.querySelector("pre.mw-syntaxhighlight.line-numbers")) {
        plugins.push("ext.SyntaxHighlight.lineNumbers");
    }
    if (document.querySelector("pre.mw-syntaxhighlight.clipboard")) {
        plugins.push("ext.SyntaxHighlight.copyToClipboard");
    }
    if (document.querySelector("pre.mw-syntaxhighlight[data-line]")) {
        plugins.push("ext.SyntaxHighlight.lineHighlight");
    }
    mw.loader
        .using(
            languages
                .map((lang) => `ext.SyntaxHighlight.${lang}`)
                .concat(plugins)
        )
        .then(() => {
            console.log("Highlighted in:", languages);
            Prism.highlightAll();
        });
};

if (document.readyState === "loading") {
    window.addEventListener("DOMContentLoaded", domLoaded, { once: true });
} else {
    domLoaded();
}
