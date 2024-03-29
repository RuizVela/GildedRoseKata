<?php
namespace jit;

use jit\node\NodeDef;
use jit\node\FunctionDef;
use jit\node\BlockDef;

/**
 * Crude parser providing some code block structure of PHP files to facilitate analysis.
 */
class Parser
{
    /**
     * The root node.
     *
     * @var object
     */
    protected $_root = null;

    /**
     * The current streamer.
     *
     * @var object
     */
    protected $_stream = null;

    /**
     * Indicate the current the current states of the parser.
     *
     * [
     *    'php'        => false,  // Indicate if the parser is in a PHP block.
     *    'class'      => false,  // Indicate if the parser is in a PHP class.
     *    'lines'      => false,  // Indicate if the parser need to process line mathing.
     *    'num'        => 0,      // Current line number.
     *    'root'       => object, // Root node.
     *    'current'    => object, // Current node.
     *    'visibility' => []      // Store function visibility.
     *    'uses'       => [],     // Maintain the uses dependencies
     *    'body'       => '',     // Maintain the current parsed content
     *    'brace'      => 0       // Depth level of opened braces
     * ]
     *
     * @var array
     */
    protected $_states = [];

    /**
     * The constructor function
     *
     * @param array $config The configuration array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'php'        => false,
            'lines'      => 0,
            'num'        => 0,
            'visibility' => [],
            'uses'       => [],
            'body'       => '',
            'brace'      => 0
        ];
        $this->_states = $config + $defaults;
        $node = new BlockDef('', 'file');
        $node->hasMethods = false;
        $this->_root = $this->_states['current'] = $node->namespace = $node;
    }

    /**
     * Parsing a file into nested nodes.
     *
     * @param  string  $content A file.
     * @param  boolean $lines   Indicate if the parser need to process line mathing.
     * @return object           The parsed file node.
     */
    protected function _parser($content, $lines = false)
    {
        $this->_initLines($content);
        $this->_stream = new TokenStream(['source' => $content, 'wrap' => $this->_states['php']]);

        while ($token = $this->_stream->current(true)) {
            $current = $this->_states['current'];
            switch ($token[0]) {
                case T_OPEN_TAG:
                case T_OPEN_TAG_WITH_ECHO:
                    $this->_codeNode();
                    $this->_states['body'] .= $token[1];
                    $this->_codeNode('open');
                    $this->_states['php'] = true;
                break;
                case T_CLOSE_TAG:
                    $this->_codeNode();
                    $this->_states['php'] = false;
                    $this->_states['body'] .= $token[1];
                    $this->_codeNode('close');
                break;
                case T_DOC_COMMENT:
                case T_COMMENT:
                    $this->_commentNode();
                break;
                case T_CONSTANT_ENCAPSED_STRING:
                    $this->_stringNode('');
                break;
                case T_START_HEREDOC:
                    $name = substr($token[1], 3, -1);
                    $this->_stringNode("\n" . $name . ';');
                break;
                case '"':
                    $this->_stringNode('"');
                break;
                case '{':
                    $this->_states['body'] .= $token[0];
                    $this->_states['current'] = $this->_codeNode();
                break;
                case '}':
                    $this->_closeCurly();
                break;
                case ';':
                    $this->_states['body'] .= $token[1];
                    $this->_codeNode(null, true);
                break;
                case T_NAMESPACE:
                    $this->_namespaceNode();
                break;
                case T_USE:
                    $this->_useNode();
                break;
                case T_TRAIT:
                    $this->_traitNode();
                break;
                case T_INTERFACE:
                    $this->_interfaceNode();
                break;
                case T_CLASS:
                    $this->_classNode();
                break;
                case T_FINAL:
                case T_ABSTRACT:
                case T_PRIVATE:
                case T_PROTECTED:
                case T_PUBLIC:
                case T_STATIC:
                    $this->_states['visibility'][$token[1]] = true;
                    $this->_states['body'] .= $token[1];
                break;
                case T_FUNCTION:
                    $this->_functionNode();
                    $buffered = '';
                break;
                case T_VARIABLE:
                    $this->_states['visibility'] = [];
                default:
                    $this->_states['body'] .= $token[1];
                break;
            }
            $this->_stream->next();
        }
        $this->_codeNode();
        $this->_flushUses();
        $this->_stream->rewind();
        $this->_assignCoverable();
        return $this->_root;
    }

    /**
     * Manage curly brackets.
     */
    protected function _closeCurly()
    {
        $current = $this->_states['current'];

        $this->_codeNode();

        $current->close = '}';

        if ($current->type === 'function') {
            if ($current->isClosure) {
                $current->close .= $this->_stream->next([')', ';', ',', ']']);
                $this->_states['num'] += substr_count($current->close, "\n");
            }
        }

        $this->_states['current'] = $current->parent;

        if (!$this->_states['lines']) {
            return;
        }
        $current->lines['stop'] = $this->_states['num'];
        $current->parent->lines['stop'] = $this->_states['num'];
    }

    /**
     * Manage use statement.
     */
    protected function _useNode()
    {
        $current = $this->_states['current'];
        $token = $this->_stream->current(true);
        $last = $alias = $use = '';
        $as = false;
        $stop = ';';
        while ($token[1] !== $stop) {
            $this->_states['body'] .= $token[1];
            if (!$token = $this->_stream->next(true)) {
                break;
            }
            switch ($token[0]) {
                case ',':
                    $as ? $this->_states['uses'][$alias] = $use : $this->_states['uses'][$last] = $use;
                    $last = $alias = $use = '';
                    $as = false;
                break;
                case T_STRING:
                    $last = $token[1];
                case T_NS_SEPARATOR:
                    $as ? $alias .= $token[1] : $use .= $token[1];
                break;
                case T_AS:
                    $as = true;
                break;
                case '{':
                    $stop = '}';
                break;
            }
        }
        $this->_states['body'] .= $token[0];
        $as ? $this->_states['uses'][$alias] = $use : $this->_states['uses'][$last] = $use;
        $this->_codeNode('use');
    }

    /**
     * Build a namespace node.
     */
    protected function _namespaceNode()
    {
        $this->_codeNode();
        $this->_flushUses();
        $body = $this->_stream->current();
        $name = $this->_stream->next([';', '{']);
        $this->_states['body'] .= $body;
        $node = new BlockDef($body . $name, 'namespace');
        $node->hasMethods = false;
        $node->name = trim(substr($name, 0, -1));
        $this->_states['current'] = $this->_root;
        $this->_contextualize($node);
        return $this->_states['current'] = $node->namespace = $node;
    }

    /**
     * Attache the founded uses to the current namespace.
     */
    protected function _flushUses()
    {
        if ($this->_states['current'] && $this->_states['current']->namespace) {
            $this->_states['current']->namespace->uses = $this->_states['uses'];
            $this->_states['uses'] = [];
        }
    }

    /**
     * Build a trait node.
     */
    protected function _traitNode()
    {
        $this->_codeNode();

        $token = $this->_stream->current(true);
        $body = $token[1];
        $body .= $this->_stream->skipWhitespaces();
        $body .= $name = $this->_stream->current();
        $body .= $this->_stream->next([';', '{']);
        $this->_states['body'] .= $body;
        $node = new BlockDef($body, 'trait');
        $node->name = $name;
        return $this->_states['current'] = $this->_contextualize($node);
    }

    /**
     * Build an interface node.
     */
    protected function _interfaceNode()
    {
        $this->_codeNode();
        $token = $this->_stream->current(true);
        $body = $token[1];
        $body .= $this->_stream->skipWhitespaces();
        $body .= $name = $this->_stream->current();
        $body .= $this->_stream->next(['{']);
        $this->_states['body'] .= $body;
        $node = new BlockDef($body, 'interface');
        $node->name = $name;
        return $this->_states['current'] = $this->_contextualize($node);
    }

    /**
     * Build a class node.
     */
    protected function _classNode()
    {
        if (substr($this->_states['body'], -2) === '::') { // Bails out on `::class`
            $this->_states['body'] .= 'class';
            return;
        }

        $this->_codeNode();
        $token = $this->_stream->current(true);
        $body = $token[1];
        $body .= $this->_stream->skipWhitespaces();
        $body .= $name = $this->_stream->current();
        $body .= $this->_stream->next(['{', T_EXTENDS]);
        $token = $this->_stream->current(true);
        $extends = '';
        if ($token[0] === T_EXTENDS) {
            $body .= $this->_stream->skipWhitespaces();
            $body .= $extends = $this->_stream->skipWhile([T_STRING, T_NS_SEPARATOR]);
            $body .= $this->_stream->current();
            if ($this->_stream->current() !== '{') {
                $body .= $this->_stream->next('{');
            }
        }
        $node = new BlockDef($body, 'class');
        $node->name = $name;
        $node->extends = $extends;

        $this->_states['body'] .= $body;
        return $this->_states['current'] = $this->_contextualize($node);
    }

    /**
     * Build a function node.
     */
    protected function _functionNode()
    {
        $node = new FunctionDef();
        $token = $this->_stream->current(true);
        $parent = $this->_states['current'];

        $body = $token[1];
        $name = substr($this->_stream->next('('), 0, -1);
        $body .= $name;
        $node->name = trim($name);
        $args = $this->_parseArgs();
        $node->args = $args['args'];
        $body .= $args['body'] . $this->_stream->next([';', '{']);
        $isMethod = $parent && $parent->hasMethods;
        $node->isMethod = $isMethod;
        $node->isClosure = !$node->name;
        if ($isMethod) {
            $node->visibility = $this->_states['visibility'];
            $this->_states['visibility'] = [];
        }
        $node->body = $body;
        $this->_codeNode();
        $this->_states['body'] = $body;
        $this->_contextualize($node);

        // Looking for curly brackets only if not an "abstract function"
        if ($this->_stream->current() === '{') {
            $this->_states['current'] = $node;
        }

        return $node->function = $node;
    }

    /**
     * Extracting a function/method args array from a stream.
     *
     * @return array The function/method args array.
     */
    protected function _parseArgs()
    {
        $inString = false;
        $cpt = 0;
        $last = $char = $value = $name = '';
        $args = [];
        $body = '';
        while ($token = $this->_stream->current(true)) {
            $body .= $token[1];
            switch ($token[0]) {
                case '(':
                    if ($cpt) {
                        $value .= $token[1];
                    }
                    $cpt++;
                break;
                case '=':
                    $name = $value;
                    $value = '';
                break;
                case ')':
                    $cpt--;
                    if ($cpt) {
                        $value .= $token[1];
                        break;
                    }
                case ',':
                    $value = trim($value);
                    if ($value !== '') {
                        $name ? $args[trim($name)] = $value : $args[] = $value;
                    }
                    $name = $value = '';
                break;
                default:
                    $value .= $token[1];
                break;
            }
            if ($token[1] === ')' && $cpt === 0) {
                break;
            }
            $this->_stream->next();
        }
        return compact('args', 'body');
    }

    /**
     * Build a code node.
     */
    protected function _codeNode($type = null, $coverable = false)
    {
        $body = $this->_states['body'];
        if ($body === '') {
            return;
        }

        $node = new NodeDef($body, $type ?: $this->_codeType());
        return $this->_contextualize($node, $coverable);
    }

    /**
     * Get code type from context
     *
     * @return string
     */
    protected function _codeType()
    {
        if ($this->_states['php']) {
            return $this->_states['current']->hasMethods ? 'attribute' : 'code';
        }
        return 'plain';
    }

    /**
     * Build a string node.
     */
    protected function _stringNode($delimiter = '')
    {
        $this->_codeNode();
        $token = $this->_stream->current(true);
        if (!$delimiter) {
            $this->_states['body'] = $token[1];
        } elseif ($delimiter === '"') {
            $this->_states['body'] = $token[1] . $this->_stream->next('"');
        } else {
            $this->_states['body'] = $token[1] . $this->_stream->nextSequence($delimiter);
        }

        $node = new NodeDef($this->_states['body'], 'string');
        $this->_contextualize($node);
        return $node;
    }

    /**
     * Build a comment node.
     */
    protected function _commentNode()
    {
        $this->_codeNode();
        $token = $this->_stream->current(true);
        $this->_states['body'] = $token[1];
        $node = new NodeDef($this->_states['body'], 'comment');
        return $this->_contextualize($node);
    }

    /**
     * Contextualize a node.
     */
    protected function _contextualize($node, $coverable = false)
    {
        $parent = $this->_states['current'];
        $node->namespace = $parent->namespace;
        $node->function = $parent->function;
        $node->parent = $parent;
        $node->coverable = $parent->hasMethods ? false : $coverable;
        $parent->tree[] = $node;
        $this->_assignLines($node);

        $node->inPhp = $this->_states['php'];
        $this->_states['body'] = '';
        return $node;
    }

    /**
     * Adds lines stores for root node.
     *
     * @param string $content A php file content.
     */
    protected function _initLines($content)
    {
        if (!$this->_states['lines']) {
            return;
        }
        if ($this->_states['lines']) {
            for($i = 0; $i <= substr_count($content, "\n"); $i++) {
                $this->_root->lines['content'][$i] = [
                    'nodes' => [],
                    'coverable' => false
                ];
            }
        }
    }

    /**
     * Assign the node to some lines and makes them availaible at the root node.
     *
     * @param object  $node The node to match.
     * @param string  $body The  to match.
     */
    protected function _assignLines($node) {
        if (!$this->_states['lines']) {
            return;
        }

        $body = $node->body;
        $num = $this->_states['num'];
        $lines = explode("\n", $body);
        $nb = count($lines) - 1;
        $this->_states['num'] += $nb;

        foreach ($lines as $i => $line) {
            $this->_assignLine($num + $i, $node, $line);
        }

        $node->parent->lines['stop'] = $this->_states['num'] - (trim($lines[$nb]) ? 0 : 1);
    }

    /**
     * Assign a node to a specific line.
     *
     * @param object  $node The node to match.
     * @param string  $body The  to match.
     */
    protected function _assignLine($index, $node, $line) {
        if ($node->lines['start'] === null) {
            $node->lines['start'] = $index;
        }
        $node->lines['stop'] = $index;
        if (trim($line)) {
            $this->_root->lines['content'][$index]['nodes'][] = $node;
        }
    }

    /**
     * Assign coverable data to lines.
     */
    protected function _assignCoverable() {
        if (!$this->_states['lines']) {
            return;
        }

        foreach ($this->_root->lines['content'] as $index => $value) {
            $this->_root->lines['content'][$index]['coverable'] = $this->_isCoverable($index);
        }
    }

    /**
     * Checks if a specific line is coverable.
     *
     * @param  integer $index The line to check.
     * @return boolean
     */
    protected function _isCoverable($index) {
        $coverable = false;
        foreach ($this->_root->lines['content'][$index]['nodes'] as $node) {
            if ($node->coverable && ($node->lines['stop'] === $index)) {
                $coverable = true;
            }
        }
        return $coverable;
    }

    /**
     * Parsing a file into nested nodes.
     *
     * @param  string  The php string to parse.
     * @param  boolean Indicate if the parser need to process line mathing.
     * @return object  the parsed file node.
     */
    public static function parse($content, $config = [])
    {
        $parser = new static($config);
        return $parser->_parser($content);
    }

    /**
     * Unparsing a node.
     *
     * @param  mixed  A node to unparse.
     * @return string the unparsed file.
     */
    public static function unparse($node)
    {
        return (string) $node;
    }

    /**
     * Returns a reader-friendly output for debug purpose.
     *
     * @param  mixed  A node or a php string to parse.
     * @return string the unparsed file.
     */
    public static function debug($content)
    {
        $root = is_object($content) ? $content : static::parse($content, ['lines' => true]);
        $lines = preg_split("~\n~", $content);
        $result = '';

        $abbr = [
            'file'      => 'file',
            'open'      => 'open',
            'close'     => 'close',
            'namespace' => 'namespace',
            'use'       => 'use',
            'class'     => 'class',
            'interface' => 'interface',
            'trait'     => 'trait',
            'function'  => 'function',
            'attribute' => 'a',
            'code'      => 'c',
            'comment'   => 'd',
            'plain'     => 'p',
            'string'    => 's'
        ];

        foreach ($root->lines['content'] as $num => $content) {
            $start = $stop = $line = $num + 1;
            $result .= '#' . str_pad($line, 6, ' ');
            $types = [];
            foreach ($content['nodes'] as $node) {
                $types[] = $abbr[$node->type];
                $stop = max($stop, $node->lines['stop'] + 1);
            }
            $result .= $content['coverable'] ? '*' : ' ';
            $result .= '[' . str_pad(join(',', $types), 19, ' ', STR_PAD_BOTH) . "]";
            $result .= ' ' . str_pad("#{$start} > #{$stop}", 16, ' ') . "|";
            $result .= $lines[$num] . "\n";
        }
        return $result;
    }
}
