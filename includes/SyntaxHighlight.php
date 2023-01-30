<?php
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

use MediaWiki\Hook\ParserOptionsRegisterHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;

class SyntaxHighlight implements ParserFirstCallInitHook, EditPage__showEditForm_initialHook, GetPreferencesHook, ParserOptionsRegisterHook {
    private $userOptionsLookup;

    public function __construct($userOptionsLookup) {
        $this->userOptionsLookup = $userOptionsLookup;
    }

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
    /* Mapping of SyntaxHighlight name to PrismJS name */
    const AVAILABLE_THEMES = array(
        'prism' => 'prism',
        'coy' => 'prismCoy',
        'okaidia' => 'prismOkaidia',
        'solarizedlight' => 'prismSolarizedlight',
        'tomorrow' => 'prismTomorrow'
    );

    public function onParserFirstCallInit($parser) {
        $parser->setHook('syntaxhighlight', array('SyntaxHighlight', 'onSyntaxHighlight'));
        $parser->setHook('source', array('SyntaxHighlight', 'onSyntaxHighlight'));
    }

    public function onEditPage__showEditForm_initial($editor, $out) {
        if (ExtensionRegistry::getInstance()->isLoaded('WikiEditor')) {
            $out->addModules(['ext.SyntaxHighlight.wikiEditor']);
        }
    }

    public function onGetPreferences($user, &$preferences) {
        $themes = array_combine(array_map(function ($name) {
            return 'syntaxhighlight-theme-' . $name;
        }, array_keys(self::AVAILABLE_THEMES)), array_keys(self::AVAILABLE_THEMES));
        $themes = array_merge(array('syntaxhighlight-theme-default' => 'default'), $themes);
        $preferences['syntaxhighlight-theme'] = array(
            'type' => 'select',
            'options-messages' => $themes,
            'label-message' => 'syntaxhighlight-preference-theme',
            'section' => 'rendering/advancedrendering',
            'tooltip' => 'hoge'
        );
    }

    public function onParserOptionsRegister(&$defaults, &$inCacheKey, &$lazyLoad) {
        $defaults['syntaxhighlight-theme'] = self::getDefaultTheme();
        $inCacheKey['syntaxhighlight-theme'] = true;
        $lazyLoad['syntaxhighlight-theme'] = function ($options) {
            return $this->getThemeForUser($options->getUserIdentity());
        };
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
        $clipboard = isset($argv['clipboard']);
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
            $out->addModules(['ext.SyntaxHighlight.core.js']);
            $codeTagAttrs['data-mw-syntaxhighlight-resourceloader-module'] = self::AVAILABLE_LANGUAGES[$lang];
        }

        $outputCode = Html::element('code', $codeTagAttrs, $code);

        if (!$inline) {
            $preTagAttrs = array(
                'class' => 'mw-syntaxhighlight mw-content-ltr ' . ($showLineNum ? 'line-numbers' : 'no-line-numbers') . ($clipboard ? ' clipboard' : ''),
                'dir' => 'ltr'
            );
            if ($highlight) {
                $preTagAttrs['data-line'] = $highlight;
            }
            $outputCode = Html::rawElement('pre', $preTagAttrs, $outputCode);
        }

        $out->addModuleStyles(['ext.SyntaxHighlight.core.css']);
        // cannot use addModuleStyles
        $out->addModules(['ext.SyntaxHighlight.theme.' . self::AVAILABLE_THEMES[$parser->getOptions()->getOption('syntaxhighlight-theme')]]);

        return array($outputCode, 'markerType' => 'nowiki');
    }

    public static function getDefaultTheme() {
        global $wgDefaultUserOptions, $wgSWS2ForceDarkTheme;
        return ((isset($wgSWS2ForceDarkTheme) && $wgSWS2ForceDarkTheme) || !empty($wgDefaultUserOptions['scratchwikiskin-dark-theme'])) ? 'okaidia' : 'prism';
    }

    public function getThemeForUser($user = null) {
        global $wgSWS2ForceDarkTheme;
        if (!$user) $user = RequestContext::getMain()->getUser();
        if (!$user) {
            return self::getDefaultTheme();
        }
        $lookup = $this->userOptionsLookup;
        $theme = $lookup->getOption($user, 'syntaxhighlight-theme');
        if (!$theme || $theme === 'default' || !array_key_exists($theme, self::AVAILABLE_THEMES)) {
            if ((isset($wgSWS2ForceDarkTheme) && $wgSWS2ForceDarkTheme) || $lookup->getOption($user, 'scratchwikiskin-dark-theme')) {
                return 'okaidia';
            }
            return 'prism';
        }
        return $theme;
    }
}
