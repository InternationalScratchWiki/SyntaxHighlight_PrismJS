<?php
/*
    Copyright (C) 2020-2022 apple502j All rights reversed.
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

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;

class SyntaxHighlight implements ParserFirstCallInitHook {
    /* Mapping of PrismJS language to ResourceLoader name */
    const AVAILABLE_LANGUAGES = array(
        'apacheconf' => 'apacheconf',
        'bash' => 'bash',
        'basic' => 'basic',
        'shell' => 'bash',
        'batch' => 'batch',
        'bbcode' => 'bbcode',
        'shortcode' => 'bbcode',
        'bnf' => 'bnf',
        'c' => 'c',
        'cpp' => 'cpp',
        'css' => 'css',
        'diff' => 'diff',
        'java' => 'java',
        'javascript' => 'javascript',
        'js' => 'javascript',
        'json' => 'json',
        'jsstacktrace' => 'jsstacktrace',
        'jsx' => 'jsx',
        'lua' => 'lua',
        'makefile' => 'makefile',
        'markdown' => 'markdown',
        'md' => 'markdown',
        'markup' => 'markup',
        'html' => 'markup',
        'xml' => 'markup',
        'svg' => 'markup',
        'mathml' => 'markup',
        'nasm' => 'nasm',
        'perl' => 'perl',
        'php' => 'php',
        'powershell' => 'powershell',
        'python' => 'python',
        'py' => 'python',
        'r' => 'r',
        'regex' => 'regex',
        'ruby' => 'ruby',
        'rb' => 'ruby',
        'shell-session' => 'shellSession',
        'sh-session' => 'shellSession',
        'shellsession' => 'shellSession',
        'sql' => 'sql',
        'swift' => 'swift',
        'vbnet' => 'vbnet',
        'vim' => 'vim',
        'wiki' => 'wiki',
        'yaml' => 'yaml'
    );
    /* Mapping of SyntaxHighlight name to PrismJS name */
    const ALIAS_LANGUAGES = array(
        'apache' => 'apacheconf',
        'bat' => 'batch',
        'c++' => 'cpp',
        'react' => 'jsx',
        'make' => 'makefile',
        'mediawiki' => 'wiki',
        'mw' => 'wiki'
    );

    public function onParserFirstCallInit($parser) {
        $parser->setHook('syntaxhighlight', array('SyntaxHighlight', 'onSyntaxHighlight'));
        $parser->setHook('source', array('SyntaxHighlight', 'onSyntaxHighlight'));
    }

    public function onEditPage__showEditForm_initial($editor, $out) {
        if (ExtensionRegistry::getInstance()->isLoaded('WikiEditor')) {
            $out->addModules('ext.SyntaxHighlight.wikiEditor');
        }
    }

    private static function addError(Parser $parser): void {
        $parser->addTrackingCategory('syntaxhighlight-error-category');
    }

    public static function onSyntaxHighlight(string $code, array $argv, Parser $parser, PPFrame $frame): array {
        $out = $parser->getOutput();

        $lang = 'none';
        if (isset($argv['lang'])) {
            $lang = strval($argv['lang']);
        } else if (isset($argv['language'])) {
            $lang = strval($argv['language']);
        } else {
            self::addError($parser);
        }
        $lang = strtolower($lang);
        if (array_key_exists($lang, self::ALIAS_LANGUAGES)) {
            $lang = self::ALIAS_LANGUAGES[$lang];
        }
        if (!array_key_exists($lang, self::AVAILABLE_LANGUAGES)) {
            $lang = 'none';
            self::addError($parser);
        }

        $inline = isset($argv['inline']);
        $showLineNum = isset($argv['line']) && !$inline;
        $highlight = null;
        if (isset($argv['highlight']) && !$inline) {
            $highlight = strval($argv['highlight']);
        }

        // Remove whitespace at the start and the end
        $code = trim($code);

        $codeTagAttrs = array(
            'class' => 'mw-syntaxhighlight-code mw-content-ltr language-' . $lang,
            'dir' => 'ltr'
        );
        if ($inline) {
            $codeTagAttrs['class'] .= ' mw-syntaxhighlight-inline';
        }
        if ($lang !== 'none') {
            $out->addModules('ext.SyntaxHighlight.core.js');
            $codeTagAttrs['data-mw-syntaxhighlight-resourceloader-module'] = self::AVAILABLE_LANGUAGES[$lang];
        }

        $outputCode = Html::element('code', $codeTagAttrs, $code);

        if (!$inline) {
            $preTagAttrs = array(
                'class' => 'mw-syntaxhighlight mw-content-ltr ' . ($showLineNum ? 'line-numbers' : 'no-line-numbers'),
                'dir' => 'ltr'
            );
            if ($highlight) {
                $preTagAttrs['data-line'] = $highlight;
            }
            $outputCode = Html::rawElement('pre', $preTagAttrs, $outputCode);
        }

        $out->addModuleStyles('ext.SyntaxHighlight.core.css');
        return array($outputCode, 'markerType' => 'nowiki');
    }
}
