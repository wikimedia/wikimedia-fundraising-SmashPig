<?php

namespace SmashPig\Core\Mapper;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;

/**
 * Transform YAML map files containing %placeholders% to output.
 *
 * Mapper Basic:
 *
 * Usage:
 * map-file.yaml:
 *   sample-array:
 *       some-value: %var1%
 *
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile);
 *
 * output :
 * [
 *  'some-data' => [
 *     'some-value' => 'abc',
 *   ],
 * ]
 *
 * Mapper Basic to JSON:
 *
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile, null,
 * Mapper::FORMAT_JSON);
 *
 * output:
 * {
 *  "some-data": {
 *    "some-value": "abc"
 *   }
 * }
 *
 * Mapper with Transformers:
 *
 * Transformers allow changes to %placeholder% values and formats
 * during the mapping process. Transformers can be passed as a Closure with two
 * arguments ($original,&$transformed) or they can be classes that extend
 * Transformer.
 *
 * @see \SmashPig\Core\Mapper\Transformers\AbstractTransformer
 * @see \SmashPig\Core\Mapper\Transformers\Transformer
 *
 * Usage:
 *  $uppercaseTransformer = function ( $original, &$transformed ) {
 *    foreach ( $original as $key => $value ) {
 *      $transformed[$key] = strtoupper( $transformed[$key] );
 *    }
 *  };
 *
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile,
 *   [$uppercaseTransformer]);
 *
 * output :
 * [
 *  'some-data' => [
 *     'some-value' => 'ABC',
 *   ],
 * ]
 *
 * @package SmashPig\Core
 *
 */
class Mapper {

	const FORMAT_ARRAY = 'array';

	const FORMAT_JSON = 'json';

	/**
	 * Map YAML map files containing %placeholders% to output.
	 *
	 * @param array $input key=>value vars to overwrite map file %placeholders%
	 * @param string $mapFilePath map file path
	 * @param array[callable|Transformer] $transformers
	 * @param string $outputFormat
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public static function map(
		$input,
		$mapFilePath,
		$transformers = [],
		$outputFormat = null
	) {
		$mapper = new static;
		$yaml = $mapper->loadMapFile( $mapFilePath );
		$map = $mapper->convertYamlMapToArray( $yaml );

		if ( count( $transformers ) > 0 ) {
			$mapper->setupInputTransformers( $transformers );
			$input = $mapper->applyInputTransformers( $input, $transformers );
		}

		$output = $mapper->translatePlaceholdersToInput( $map, $input );
		if ( isset( $outputFormat ) && $outputFormat != static::FORMAT_ARRAY ) {
			$output = $mapper->formatOutput( $output, $outputFormat );
		}

		return $output;
	}

	/**
	 * Load YAML map file
	 *
	 * @param $mapFilePath
	 *
	 * @return bool|string
	 * @throws \SmashPig\Core\SmashPigException
	 */
	protected function loadMapFile( $mapFilePath ) {
		$fullMapFilePath = __DIR__ . "/../../" . $mapFilePath;
		if ( !is_file( $fullMapFilePath ) ) {
			Logger::error( "File $fullMapFilePath does not exist." );
			throw new SmashPigException( "File $fullMapFilePath does not exist." );
		}

		if ( !is_readable( $fullMapFilePath ) ) {
			Logger::error( "File $fullMapFilePath cannot be read." );
			throw new SmashPigException( "File $fullMapFilePath cannot be read." );
		}

		return file_get_contents( $fullMapFilePath );
	}

	/**
	 * Transform input variables to %variable% format.
	 *
	 * @param array $inputVars
	 *
	 * @return array $formattedInputVars
	 */
	protected function formatInputVars( $inputVars ) {
		$formattedInputVars = [];
		foreach ( $inputVars as $var => $value ) {
			$formattedInputVars['%' . strtolower( $var ) . '%'] = $value;
		}
		return $formattedInputVars;
	}

	/**
	 * Convert YAML string to array
	 *
	 * @param string $yaml
	 *
	 * @return array
	 */
	protected function convertYamlMapToArray( $yaml ) {
		try {
			return Yaml::parse( $yaml, true );

		} catch ( ParseException $exception ) {
			Logger::error( 'Unable to YAML parse map file :' . $exception->getMessage() );
			throw new SmashPigException( 'Unable to YAML parse map file :' . $exception->getMessage() );
		}
	}

	/**
	 * Convert transformer paths to classes if necessary
	 *
	 * @param $transformers
	 */
	protected function setupInputTransformers( &$transformers ) {
		foreach ( $transformers as $i => $transformer ) {
			if ( is_callable( $transformer ) ) {
				// All good, we have a Transformer class or callable Closure.
				continue;
			} elseif ( is_string( $transformer ) && class_exists( $transformer ) ) {
				$transformers[$i] = new $transformer();
			} else {
				Logger::error( "Transformer supplied not callable: " . $transformer );
				throw new \InvalidArgumentException(
					"Transformers should be callable or an instance of Transformer: " . $transformer
				);
			}
		}
	}

	/**
	 * Apply any input transformations.
	 *
	 * Note: $transformed is passed between all Transformers to allow
	 * "layering" of Transformer behaviour. Due to this, within the scope
	 * of your transformer method (or Closure transformer) always refer to
	 * $transformed['field'] for the latest version of that value and only
	 * use $original['field'] when you want to know the original value prior to
	 * any Transformations being applied.
	 *
	 * @param $input
	 * @param $transformers
	 *
	 * @return mixed
	 */
	protected function applyInputTransformers( $input, $transformers ) {
		$transformed = $original = $input;
		foreach ( $transformers as $transformer ) {
			// $transformed passed by reference
			$transformer( $original, $transformed );
		}
		return $transformed;
	}

	/**
	 * Translate map %placeholders% to input values and clear out any
	 * unset/unused %placeholders% strings
	 *
	 * @param $map
	 * @param $input
	 *
	 * @return string
	 */
	protected function translatePlaceholdersToInput( $map, $input ) {
		$input = $this->formatInputVars( $input );

		// walk through map setting %placeholder% values as either the
		// corresponding input value, or null if no matching input value set.
		array_walk_recursive( $map, function ( &$value ) use ( $input ) {
			if ( preg_match( "/%[^%]+%/", $value ) === 1 ) {
				$value = array_key_exists( $value, $input ) ? $input[$value] : null;
			}
		} );

		return $map;
	}

	/**
	 * Transform output to desired format.
	 *
	 * @param $output
	 * @param $format
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 * @throws \Symfony\Component\Yaml\Exception\ParseException
	 */
	protected function formatOutput( $output, $format ) {
		switch ( $format ) {
			case static::FORMAT_JSON:
				return json_encode( $output );
				break;
			default:
				Logger::error( "Invalid Mapper output format supplied: " . $format );
				throw new \InvalidArgumentException( "Invalid Mapper output format supplied: " . $format );
		}
	}

}