<?php
/*
    Copyright (C) 2020 apple502j All rights reversed.
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
class SyntaxHighlight {
    /* Mapping of PrismJS language to ResourceLoader name */
    const AVAILABLE_LANGUAGES = array(
        'bash' => 'bash',
        'shell' => 'bash',
        'bbcode' => 'bbcode',
        'shortcode' => 'bbcode',
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
        'markdown' => 'markdown',
        'md' => 'markdown',
        'markup' => 'markup',
        'html' => 'markup',
        'xml' => 'markup',
        'svg' => 'markup',
        'mathml' => 'markup',
        'php' => 'php',
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
        'wiki' => 'wiki'
    );
    /* Mapping of SyntaxHighlight name to PrismJS name */
    const ALIAS_LANGUAGES = array(
        'c++' => 'cpp',
        'react' => 'jsx',
        'mediawiki' => 'wiki',
        'mw' => 'wiki'
    );

    public static function onParserFirstCallInit(Parser $parser) : void {
        $parser->setHook('syntaxhighlight', array('SyntaxHighlight', 'onSyntaxHighlight'));
        $parser->setHook('source', array('SyntaxHighlight', 'onSyntaxHighlight'));
    }

    private static function addError(Parser $parser) : void {
        $parser->addTrackingCategory('syntaxhighlight-error-category');
    }

    public static function onSyntaxHighlight(string $code, array $argv, Parser $parser, PPFrame $frame) : array {
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
            'class' => 'mw-syntaxhighlight-code language-' . $lang,
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
                'class' => 'mw-syntaxhighlight ' . ($showLineNum ? 'line-numbers' : 'no-line-numbers'),
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
