mw.hook('wikiEditor.toolbarReady').add(function ($textarea) {
    $textarea.wikiEditor('addToToolbar', {
        section: 'advanced',
        group: 'insert',
        tools: {
            smile: {
                label: mw.message( 'syntaxhighlight-wikieditor-button' ).text(),
                type: 'button',
                oouiIcon: 'code',
                action: {
                    type: 'encapsulate',
                    options: {
                        pre: "<syntaxhighlight lang=\"\">\n",
                        post: "\n</syntaxhighlight>",
                        ownline: true
                    }
                }
            }
        }
    });
});
