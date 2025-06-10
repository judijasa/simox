<?php
// Topological sorting method to
// build schemas in an order
// consistent with dependencies.
// Code written with the assitance of ChatGPT.
class Graph {
    public $adjacency_list = array();

    public function add_edge($source, $destination) {
        if (!array_key_exists($source, $this->adjacency_list)) {
            $this->adjacency_list[$source] = array();
        }
        array_push($this->adjacency_list[$source], $destination);
    }

    public function topological_sort() {
        $visited = array();
        $stack = new SplStack();

        foreach ($this->adjacency_list as $node => $neighbors) {
            if (!isset($visited[$node])) {
                $this->dfs_topological_sort($node, $visited, $stack);
            }
        }

        $result = array();
        while (!$stack->isEmpty()) {
            $result[] = $stack->pop();
        }
        unset($result[0]); # remove root schema from output
        $result = array_values($result);
        $result = array_reverse($result);

        return $result;
    }

    private function dfs_topological_sort($node, &$visited, &$stack) {
        $visited[$node] = true;
        if (isset($this->adjacency_list[$node])) {
            foreach ($this->adjacency_list[$node] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $this->dfs_topological_sort($neighbor, $visited, $stack);
                }
            }
        }
        $stack->push($node);
    }
}

function extract_dependencies($schema) {
    require_once 'pkg/'. $schema. '/default.php';
    return $dependencies;
}

function build_dependency_graph($root_schema) {
    $graph = new Graph();
    $visited = array();
    $stack = new SplStack();
    $stack->push($root_schema);

    while (!$stack->isEmpty()) {
        $current_schema = $stack->pop();
        if (isset($visited[$current_schema])) {
            continue;
        }
        $visited[$current_schema] = true;
        $dependencies = extract_dependencies($current_schema);
        foreach ($dependencies as $dependency) {
            $graph->add_edge($current_schema, $dependency);
            $dependency_path = 'pkg/'. $dependency. '/default.php';
            if (file_exists($dependency_path)) {
                $stack->push($dependency);
            }
        }
    }

    return $graph;
}

if (!count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))) {
    $root_schema = 'simo-C196A24801D24B16';

    $dependency_graph = build_dependency_graph($root_schema);

    // Print the dependency graph
    echo "Dependency Graph:\n";
    foreach ($dependency_graph->adjacency_list as $node => $dependencies) {
        echo "$node -> " . implode(", ", $dependencies) . "\n";
    }

    // Perform topological sort and print the result
    echo "\nTopological Ordering:\n";
    $result = $dependency_graph->topological_sort();
    echo implode(", ", $result) . "\n";
}
?>
