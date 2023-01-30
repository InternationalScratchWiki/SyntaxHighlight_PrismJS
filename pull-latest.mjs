import fs from "fs/promises";
import fetch from "node-fetch";
import { camelCase } from "camel-case";

/*
    Copyright (C) 2020-2023 apple502j All rights reversed.
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

const PLUGINS = [
    "line-highlight",
    "line-numbers",
    "toolbar",
    "copy-to-clipboard",
];
const LANGUAGES = [
    "apacheconf",
    "core",
    "bash",
    "batch",
    "bbcode",
    "bnf",
    "c",
    "clike",
    "cpp",
    "css",
    "css-extras",
    "diff",
    "java",
    "javascript",
    "js-extras",
    "json",
    "jsstacktrace",
    "jsx",
    "lua",
    "makefile",
    "markdown",
    "markup",
    "markup-templating",
    "nasm",
    "perl",
    "php",
    "php-extras",
    "powershell",
    "python",
    "r",
    "regex",
    "ruby",
    "shell-session",
    "sql",
    "swift",
    "vbnet",
    "vim",
    "wiki",
    "yaml",
];
const EXTRAS = {
    css: "css-extras",
    javascript: "js-extras",
    php: "php-extras",
};
const DEPENDENCIES = {
    c: ["clike"],
    cpp: ["c"],
    java: ["clike"],
    javascript: ["clike"],
    markdown: ["markup"],
    "markup-templating": ["markup"],
    php: ["markup-templating"],
    jsx: ["markup", "javascript"],
    ruby: ["clike"],
    "shell-session": ["bash"],
    swift: ["clike"],
    vbnet: ["basic"],
    wiki: ["markup"],

    "copy-to-clipboard": ["toolbar"],
};

if (process.argv[2] !== "-Nd") {
    // Part 1: Download
    const write = (fn, data) => {
        console.log("Writing:", fn);
        return fs.writeFile(`./resources/${fn}`, data, { encoding: "utf8" });
    };

    await Promise.all([
        fetch(
            "https://raw.githubusercontent.com/PrismJS/prism/master/themes/prism.css"
        )
            .then((resp) => resp.text())
            .then((txt) => write("prism.css", txt)),
        ...PLUGINS.flatMap((name) => [
            fetch(
                `https://raw.githubusercontent.com/PrismJS/prism/master/plugins/${name}/prism-${name}.min.js`
            )
                .then((resp) => resp.text())
                .then((txt) => write(`prism-${name}.min.js`, txt)),
            fetch(
                `https://raw.githubusercontent.com/PrismJS/prism/master/plugins/${name}/prism-${name}.css`
            )
                .then((resp) => resp.text())
                .then((txt) => write(`prism-${name}.css`, txt)),
        ]),
        ...LANGUAGES.map((name) =>
            fetch(
                `https://raw.githubusercontent.com/PrismJS/prism/master/components/prism-${name}.min.js`
            )
                .then((resp) => resp.text())
                .then((txt) => write(`prism-${name}.min.js`, txt))
        ),
    ]);
}

// Part 2: Generate extension.json
const extensionJSONBase = await fs.readFile("./extension_base.json", "utf8");
const extensionJSON = JSON.parse(extensionJSONBase);
extensionJSON.ResourceModules = {
    "ext.SyntaxHighlight.core": {
        styles: "prism.css",
        packageFiles: "prism-core.min.js",
        localBasePath: "resources",
        remoteExtPath: "SyntaxHighlight_PrismJS/resources",
    },
    "ext.SyntaxHighlight.core.css": {
        styles: "syntaxhighlight-core.css",
        localBasePath: "resources",
        remoteExtPath: "SyntaxHighlight_PrismJS/resources",
    },
    "ext.SyntaxHighlight.core.js": {
        packageFiles: "syntaxhighlight-core.js",
        localBasePath: "resources",
        remoteExtPath: "SyntaxHighlight_PrismJS/resources",
    },
    "ext.SyntaxHighlight.wikiEditor": {
        packageFiles: "syntaxhighlight-wikieditor.js",
        localBasePath: "resources",
        remoteExtPath: "SyntaxHighlight_PrismJS/resources",
        dependencies: ["ext.wikiEditor"],
        messages: ["syntaxhighlight-wikieditor-button"],
    },
};

const solveDependencies = (name) =>
    DEPENDENCIES.hasOwnProperty(name)
        ? DEPENDENCIES[name].map(
              (dependency) => `ext.SyntaxHighlight.${camelCase(dependency)}`
          )
        : [];

PLUGINS.forEach((pluginName) => {
    extensionJSON.ResourceModules[
        `ext.SyntaxHighlight.${camelCase(pluginName)}`
    ] = {
        styles: `prism-${pluginName}.css`,
        packageFiles: `prism-${pluginName}.min.js`,
        localBasePath: "resources",
        remoteExtPath: "SyntaxHighlight_PrismJS/resources",
        dependencies: [
            "ext.SyntaxHighlight.core",
            ...solveDependencies(pluginName),
        ],
    };
});

LANGUAGES.forEach((langName) => {
    if (langName === "core" || langName.endsWith("-extras")) return;
    const packageFiles = [`prism-${langName}.min.js`];
    const dependencies = [
        "ext.SyntaxHighlight.core",
        ...solveDependencies(langName),
    ];
    if (EXTRAS.hasOwnProperty(langName)) {
        packageFiles.push(`prism-${EXTRAS[langName]}.min.js`);
    }
    extensionJSON.ResourceModules[
        `ext.SyntaxHighlight.${camelCase(langName)}`
    ] = {
        packageFiles,
        dependencies,
        localBasePath: "resources",
        remoteExtPath: "SyntaxHighlight_PrismJS/resources",
    };
});

console.log("Generated extension.json");
await fs.writeFile(
    "extension.json",
    JSON.stringify(extensionJSON, null, 4),
    "utf8"
);
