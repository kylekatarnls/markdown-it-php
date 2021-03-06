<?php
/**
 * Copyright (c) 2014-2015 Vitaly Puzrin, Alex Kocharin.
 *
 * This software is released under the MIT License.
 * http://opensource.org/licenses/mit-license.php
 */
/**
 * Copyright (c) 2016 kaoken
 *
 * This software is released under the MIT License.
 * http://opensource.org/licenses/mit-license.php
 *
 *
 * use javascript version 2.0.0
 * @see https://github.com/markdown-it/markdown-it-ins/tree/2.0.0
 */
namespace Kaoken\MarkdownIt\Plugins;


class MarkdownItIns
{

    /**
     * @param \Kaoken\MarkdownIt\MarkdownIt $md
     */
    public function plugin($md)
    {
        $md->inline->ruler->before('emphasis', 'ins', [$this, 'tokenize']);
        $md->inline->ruler2->before('emphasis', 'ins', [$this, 'postProcess']);
    }

    /**
     * Insert each marker as a separate text token, and add it to delimiter list
     * @param \Kaoken\MarkdownIt\RulesInline\StateInline $state
     * @param bool $silent
     * @return bool
     */
    function tokenize($state, $silent=false)
    {
        $start = $state->pos;
        $marker = $state->src[$start];

        if ($silent) { return false; }

        if ($marker !== '+') { return false; }

        $scanned = $state->scanDelims($state->pos, true);
        $len = $scanned->length;
        $ch = $marker;

        if ($len < 2) { return false; }

        if ($len % 2) {
            $token         = $state->push('text', '', 0);
            $token->content = $ch;
            $len--;
        }

        for ($i = 0; $i < $len; $i += 2) {
            $token         = $state->push('text', '', 0);
            $token->content = $ch . $ch;

            $obj = new \stdClass();
            $obj->marker = $marker;
            $obj->jump = $i;
            $obj->token = count($state->tokens) - 1;
            $obj->level =  $state->level;
            $obj->end =  -1;
            $obj->open = $scanned->can_open;
            $obj->close = $scanned->can_close;
            $state->delimiters[] = $obj;
    }

        $state->pos += $scanned->length;

        return true;
    }


    /**
     * Walk through delimiter list and replace text tokens with tags
     * @param \Kaoken\MarkdownIt\RulesInline\StateInline $state
     */
    function postProcess($state)
    {
        $loneMarkers = [];
        $delimiters = &$state->delimiters;
        $max = count($state->delimiters);

        for ($i = 0; $i < $max; $i++) {
            $startDelim = $delimiters[$i];

            if ($startDelim->marker !== '+') {
                continue;
            }

            if ($startDelim->end === -1) {
                continue;
            }

            $endDelim = $delimiters[$startDelim->end];

            $token         = $state->tokens[$startDelim->token];
            $token->type    = 'ins_open';
            $token->tag     = 'ins';
            $token->nesting = 1;
            $token->markup  = '++';
            $token->content = '';

            $token         = $state->tokens[$endDelim->token];
            $token->type    = 'ins_close';
            $token->tag     = 'ins';
            $token->nesting = -1;
            $token->markup  = '++';
            $token->content = '';

            if ($state->tokens[$endDelim->token - 1]->type === 'text' &&
                $state->tokens[$endDelim->token - 1]->content === '+') {

                $loneMarkers[] = $endDelim->token - 1;
            }
        }

        // If a $marker sequence has an odd number of characters, it's splitted
        // like this: `~~~~~` -> `~` + `~~` + `~~`, leaving one $marker at the
        // $start of the sequence.
        //
        // So, we have to move all those markers after subsequent s_close tags.
        //
        while (count($loneMarkers)) {
            $i = array_pop($loneMarkers);
            $j = $i + 1;

            while ($j < count($state->tokens) && $state->tokens[$j]->type === 'ins_close') {
                $j++;
            }

            $j--;

            if ($i !== $j) {
                $token = $state->tokens[$j];
                $state->tokens[$j] = $state->tokens[$i];
                $state->tokens[$i] = $token;
            }
        }
    }
}