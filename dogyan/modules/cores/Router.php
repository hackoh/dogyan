<?php
class Router {	
	public static function dispatch(array $routes = array(), $request) {

		// Delete basedir span from request
		$baseDir = dirname($_SERVER['SCRIPT_NAME']);
		if (!empty($baseDir))
			$request = substr_replace($request, '', strpos($request, $baseDir), strlen($baseDir));
		
		// Request first character must be "/"
		if ($request === '' || $request{0} !== '/')
			$request = '/' . $request;
		
		// Parse get variables
		$getVars = array();
		if (strpos($request, '?') !== false) {
			$requests = explode('?', $request);
			$request = $requests[0];
			
			if (strlen($requests[1]) !== 0) {
				$getParams = explode('&', $requests[1]);
				foreach ($getParams as $var) {
					if (strpos($var, '=') !== false)
						list($name, $val) = explode('=', $var);
					else
						list($name, $val) = array($var, null);
					$getVars[$name] = $val;
				}
			}
		}
		// Parse routes
		foreach ($routes as $path => $target) {
			$regex = $path;
			
			// Arguments
			$argCount = 0;
			while (strpos($regex, ':num') !== false)
				$regex = substr_replace($regex, '(?P<arg' . $argCount++ . '>[0-9]+)', strpos($regex, ':num'), 4);
			while (strpos($regex, ':any') !== false)
				$regex = substr_replace($regex, '(?P<arg' . $argCount++ . '>[^/]+)', strpos($regex, ':any'), 4);
			
			// Default
			$regex = str_replace(':controller', '(?P<controller>[a-zA-Z][a-zA-Z0-9_-]+)', $regex);
			$regex = str_replace(':action', '(?P<action>[a-zA-Z_][a-zA-Z0-9_-]+)', $regex);
			
			$arguments = array();
			$captures = array();
			$extension = '';
			$nextIsPair = false;
			if (!preg_match('#^' . $regex . '$#', $request, $matches)) continue;
			while ($match = each($matches)) {
				if ($nextIsPair) {
					$nextIsPair = false;
					continue;
				}
				if (!is_int($match['key'])) {
					$nextIsPair = true;
					// Controller
					if ($match['key'] === 'controller') {
						$target = str_replace(':controller', $match['value'], $target);
						$target = str_replace(':Controller', ucfirst($match['value']), $target);
						continue;
					}
					// Action
					if ($match['key'] === 'action') {
						$target = str_replace(':action', $match['value'], $target);
						continue;
					}
					// Extension
					if ($match['key'] === 'extension') {
						$extension = $match['value'];
						continue;
					}
					// Arguments
					$arguments[] = $match['value'];
					continue;
				}
				$captures[] = $match['value'];
			}
			// Fix controller|action $x
			foreach ($captures as $index => $r)
				$target = str_replace('$' . $index, $r, $target);
			break;
		}
		// Fix Extension
		$extension = explode('.', ltrim($extension, '.'));
		if (count($extension) === 1)
			$extension = $extension[0];
		
		$parsed = array(
			'request' => $request,
			'get' => $getVars,
			'target' => $target,
			'captures' => $captures,
			'arguments' => $arguments,
			'regex' => $regex,
			'extension' => $extension
		);
		return $parsed;
	}
}