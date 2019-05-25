<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();


// Note that is a complete rewrite of cassession, in this we generate 
// no "caching" in the form of keyval representations as we do not 
// necessarily return enough information from the CAS to do that, for 
// that matter neither did the old one...


// @copyright  2019 Aalto University.
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.

require_once(__DIR__ . '/connectorhelper.class.php');
require_once(__DIR__ . '/../options.class.php');
require_once(__DIR__ . '/../utils.class.php');
require_once(__DIR__ . '/evaluatable_object.interfaces.php');

class stack_cas_session2 {
  
    private $statements;

    private $instantiated;

    private $options;

    private $seed;

    private $errors;

    public function __construct(array $statements, stack_options $options = null, $seed = null) {

        $this->instantiated = false;
        $this->errors = array();
        $this->statements = $statements;

        foreach ($statements as $statement) {
            if (!is_subclass_of($statement, 'cas_evaluatable')) {
                throw new stack_exception('stack_cas_session: items in $statements must be cas_evaluatable.');
            }
        }

        if ($options === null) {
            $this->options = new stack_options();
        } else if (is_a($options, 'stack_options')) {
            $this->options = $options;
        } else {
            throw new stack_exception('stack_cas_session: $options must be stack_options.');
        }

        if (!($seed === null)) {
            if (is_int($seed)) {
                $this->seed = $seed;
            } else {
                throw new stack_exception('stack_cas_session: $seed must be a number.  Got "'.$seed.'"');
            }
        } else {
            $this->seed = time();
        }
    }

    public function get_session(): array {
        return $this->statements;
    }

    public function add_statement(cas_evaluatable $statement, bool $append = true) {
        if ($append) {
            $this->statements[] = $statement;
        } else {
            array_unshift($this->statements, $statement);
        }
        $this->instantiated = false;
    }

    public function add_statements(array $statements, bool $append = true) {
        foreach ($statements as $statement) {
            if (!is_subclass_of($statement, 'cas_evaluatable')) {
                throw new stack_exception('stack_cas_session: items in $statements must be cas_evaluatable.');
            }
        }

        if ($append) {
            $this->statements = array_merge($this->statements, $statements);
        } else {
            $this->statements = array_merge($statements, $this->statements);
        }
        $this->instantiated = false;
    }

    public function prepend_to_session(stack_cas_session2 $target) {
        $target->statements = array_merge($this->statements, $target->statements);
    }

    public function append_to_session(stack_cas_session2 $target) {
        $target->statements = array_merge($target->statements, $this->statements);
    }

    public function get_variable_usage(array &$update_array = array()): array {
        foreach ($this->statements as $statement) {
            $update_array = $statement->get_variable_usage($update_array);
        }
        return $update_array;
    }

    public function is_instantiated(): bool {
        return $this->instantiated;
    }

    public function get_valid(): bool {
        foreach ($this->statements as $statement) {
            if ($statement->get_valid() === false) {
                return false;
            }
        }
        // There is nothing wrong with an empty session.
        return true;
    }

    public function get_by_key(string $key): ?cas_evaluatable {
        // Searches from the statements the last one with a given key.
        // This is a concession for backwards compatibility and should not be used.
        $found = null;
        foreach ($this->statements as $statement) {
            if ($statement->get_key() === $key) {
                $found = $statement;
            }
        }
        return $found;
    }

    /**
     * Returns all the errors related to evaluation. Naturally only call after
     * instanttiation.
     */
    public function get_errors($implode = true) {
        if ($implode !== true) {
            return $this->errors;
        }
        $r = array();
        foreach ($this->errors as $value) {
            // [0] the list of errors
            // [1] the context information
            // [2] the statement number
            $r[] = implode(' ', $value[0]);
        }
        return implode(' ', $r);
    }

    /**
     * Executes this session and returns the values to the statements that 
     * request them.
     * Returns true if everything went well.
     */
    public function instantiate(): bool {
        if (count($this->statements) === 0 || $this->instantiated === true) {
            $this->instantiated = true;
            return true;
        }
        if (!$this->get_valid()) {
            throw new stack_exception('stack_cas_session: cannot instantiate invalid session');
        }

        // Lets simply build the expression here.
        // NOTE that we do not even bother trying to protect the global scope
        // as we have not seen anyone using the same CAS process twice, should 
        // that become necessary much more would need to be done. But the parser 
        // can handle that if need be.
        $collectvalues = array();
        $collectlatex = array();

        $i = 0;
        foreach ($this->statements as $statement) {
            if ($statement instanceof cas_value_extractor) {
                $collectvalues['__e_smt_' . $i] = $statement;
            }
            if ($statement instanceof cas_latex_extractor) {
                $collectlatex['__e_smt_' . $i] = $statement;
            }
            $i = $i + 1;
        }

        // We will build the whole command here, note that the preamble should
        // go to the library.
        $command = '_EC(ec,sco,sta):=if is(ec=[]) then (_ERR:append(_ERR,[[error,sco,sta]]),false) else true$';

        // We need to collect some answernotes so lets redefine this funtion:
        $command .= 'StackAddFeedback(fb,key,[ex]):=block([str,exprs,jloop],exprs:"",ev(for jloop:1 thru length(ex) do exprs: sconcat(exprs," , !quot!",ex[jloop],"!quot! "),simp),str:sconcat(fb, "stack_trans(\'",key,"\'", exprs, "); !NEWLINE!"),__NOTES:append(__NOTES,[sconcat("stack_trans(\'",key,"\'", exprs, ");")]),return(str))$';


        // No protection in the block.
        $command .= 'block([],stack_randseed(' . $this->seed . ')';
        // The options.
        $command .=  $this->options->get_cas_commands()['commands'];
        // Some parts of logic storage:
        $command .= ',_ERR:[],_RESPONSE:["stack_map"]';
        $command .= ',_NOTES:["stack_map"]';
        $command .= ',_VALUES:["stack_map"]';
        if (count($collectlatex) > 0) {
            $command .= ',_LATEX:["stack_map"]';
        }

        // Set some values:
        $command .= ',_VALUES:stackmap_set(_VALUES,"__stackmaximaversion",stackmaximaversion)';

        // Evaluate statements.
        $i = 0;
        foreach ($this->statements as $num => $statement) {
            $ef = $statement->get_evaluationform();
            $line = ',_EC(errcatch(' . $ef . '),';
            if (($statement instanceof cas_value_extractor) || ($statement instanceof cas_latex_extractor)) {
                // One of those that need to be collected later.
                $line = ',__NOTES:[],_EC(errcatch(__e_smt_' . $i . ':' . $ef . '),';
            }
            $line .= stack_utils::php_string_to_maxima_string($statement->get_source_context());
            $line .= ',' . $i . ')';
            if (($statement instanceof cas_value_extractor) || ($statement instanceof cas_latex_extractor)) {
                // If this is one of those that we collect answernotes for.
                $line .= ',if length(__NOTES) > 0 then _NOTES:stackmap_set(_NOTES,"__e_smt_' . $i . '",__NOTES)';
            }

            $command .= $line;
            $i = $i + 1;
        }

        // Collect values if required.
        foreach ($collectvalues as $key => $statement) {
            $command .= ',_VALUES:stackmap_set(_VALUES,';
            $command .= stack_utils::php_string_to_maxima_string($key);
            $command .= ',string(' . $key . '))';
        }
        foreach ($collectlatex as $key => $statement) {
            $command .= ',_LATEX:stackmap_set(_LATEX,';
            $command .= stack_utils::php_string_to_maxima_string($key);
            $command .= ',tex1(' . $key . '))';
        }

        // Pack values to the response.
        $command .= ',_RESPONSE:stackmap_set(_RESPONSE,"timeout",false)';
        $command .= ',_RESPONSE:stackmap_set(_RESPONSE,"values",_VALUES)';
        $command .= ',if length(_NOTES)>1 then _RESPONSE:stackmap_set(_RESPONSE,"answernotes",_NOTES)';
        if (count($collectlatex) > 0) {
            $command .= ',_RESPONSE:stackmap_set(_RESPONSE,"presentation",_LATEX)';
        }
        $command .= ',if length(_ERR)>0 then _RESPONSE:stackmap_set(_RESPONSE,"errors",_ERR)';

        // Then output them.
        $command .= ',print("STACK-OUTPUT-BEGINS>")';
        $command .= ',print(stackjson_stringify(_RESPONSE))';
        $command .= ',print("<STACK-OUTPUT-ENDS")';
        $command .= ')$';

        // Send it to cas.
        $connection = stack_connection_helper::make();
        $results = $connection->json_compute($command);

        // Lets collect what we got.
        $asts = array();
        $latex = array();
        $ersby_statement = array();
        $notesby_statement = array();

        if ($results['timeout'] === true) {
            foreach ($this->statements as $num => $statement) {
                $statement->set_cas_status(array("TIMEDOUT"), array());
            }
        } else {
            if (array_key_exists('values', $results)) {
                foreach ($results['values'] as $key => $value) {
                    if (is_string($value)) {
                        $ast = maxima_parser_utils::parse($value);
                        // Lets unpack the MP_Root immediately.
                        $asts[$key] = $ast->items[0];
                    }
                }
            }
            if (array_key_exists('presentation', $results)) {
                foreach ($results['presentation'] as $key => $value) {
                    if (is_string($value)) {
                        $latex[$key] = $value;
                    }
                }
            }
            if (array_key_exists('errors', $results)) {
                $this->errors = $results['errors'];
                foreach ($results['errors'] as $key => $value) {
                    // [0] the list of errors
                    // [1] the context information
                    // [2] the statement number
                    $ersby_statement[$value[2]] = $value[0];
                }
            }
            if (array_key_exists('answernotes', $results)) {
                foreach ($results['answernotes'] as $key => $value) {
                    $notesby_statement[intval(substr($key, strlen('__e_smt_')))] = $value;
                }
            }

            // Then push those to the objects we are handling.
            foreach ($this->statements as $num => $statement) {
                $err = array();
                if (array_key_exists($num, $ersby_statement)) {
                    $err = $ersby_statement[$num];
                }
                $answernotes = array();
                if (array_key_exists($num, $notesby_statement)) {
                    $answernotes = $notesby_statement[$num];
                }
                $statement->set_cas_status($err, $answernotes);
            }
            
            foreach ($collectvalues as $key => $statement) {
                $statement->set_cas_evaluated_value($asts[$key]);
            }
            foreach ($collectlatex as $key => $statement) {
                $statement->set_cas_latex_value($latex[$key]);
            }
            
            $this->instantiated = true;
        }
        return $this->instantiated;
    }


    public function get_debuginfo() {
        // TODO...
        return '';
    }
}