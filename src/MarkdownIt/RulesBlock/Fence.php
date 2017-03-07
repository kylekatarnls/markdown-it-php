<?php
// fences (``` lang, ~~~ lang)

namespace Kaoken\MarkdownIt\RulesBlock;

use Kaoken\MarkdownIt\Common\Utils;

class Fence
{
    /**
     * @param StateBlock $state
     * @param integer $startLine
     * @param integer $endLine
     * @param boolean $silent
     * @return bool
     */
    public function set(&$state, $startLine, $endLine, $silent=false)
    {
        $haveEndMarker = false;
        $pos = $state->bMarks[$startLine] + $state->tShift[$startLine];
        $max = $state->eMarks[$startLine];

        // if it's indented more than 3 spaces, it should be a code block
        if ($state->sCount[$startLine] - $state->blkIndent >= 4) { return false; }

        if ($pos + 3 > $max) { return false; }

        $marker = $state->src[$pos];

        if ($marker !== '~' && $marker !== '`') {
            return false;
        }

        // scan $marker length
        $mem = $pos;
        $pos = $state->skipChars($pos, $marker);

        $len = $pos - $mem;

        if ($len < 3) { return false; }

        $markup = substr($state->src, $mem, $pos-$mem);
        $params = substr($state->src, $pos, $max-$pos);

        if ( strpos($params, $marker) !== false) { return false; }

        // Since start is found, we can report success here in validation mode
        if ($silent) { return true; }

        // search end of block
        $nextLine = $startLine;

        while (true) {
            $nextLine++;
            if ($nextLine >= $endLine) {
                // unclosed block should be autoclosed by end of document.
                // also block seems to be autoclosed by end of parent
                break;
            }

            $pos = $mem = $state->bMarks[$nextLine] + $state->tShift[$nextLine];
            $max = $state->eMarks[$nextLine];

            if ($pos < $max && $state->sCount[$nextLine] < $state->blkIndent) {
                // non-empty line with negative indent should stop the list:
                // - ```
                //  test
                break;
            }

            if ($state->src[$pos]!== $marker) { continue; }

            if ($state->sCount[$nextLine] - $state->blkIndent >= 4) {
                // closing fence should be indented less than 4 spaces
                continue;
            }

            $pos = $state->skipChars($pos, $marker);

            // closing code fence must be at least as long as the opening one
            if ($pos - $mem < $len) { continue; }

            // make sure tail has spaces only
            $pos = $state->skipSpaces($pos);

            if ($pos < $max) { continue; }

            $haveEndMarker = true;
            // found!
            break;
        }

        // If a fence has heading spaces, they should be removed from its inner block
        $len = $state->sCount[$startLine];

        $state->line = $nextLine + ($haveEndMarker ? 1 : 0);

        $token         = $state->push('fence', 'code', 0);
        $token->info    = $params;
        $token->content = $state->getLines($startLine + 1, $nextLine, $len, true);
        $token->markup  = $markup;
        $token->map     = [ $startLine, $state->line ];

        return true;
    }
}