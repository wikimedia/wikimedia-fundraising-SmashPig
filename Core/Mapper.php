<?php

namespace SmashPig\Core;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use SmashPig\Core\Logging\Logger;

/**
 * Transform YAML map files containing %placeholders% to output.
 *
 *  Usage:
 *  map-file.yaml:
 *   some-data:
 *       some-value: %var1%
 *
 * To array:
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile);
 *
 * output :
 * [
 *  'some-data' => [
 *     'some-value' => 'abc',
 *   ],
 * ]
 *
 * To JSON:
 * $output = Mapper::map(['var1'=>'abc'], $pathToMapFile, Mapper::FORMAT_JSON);
 *
 * output:
 * {
 *  "some-data": {
 *    "some-value": "abc"
 *   }
 * }
 *
 * @package SmashPig\Core
 *
 * @group Mapper
 */
class Mapper {

	const FORMAT_ARRAY = 'array';

	const FORMAT_JSON = 'json';

	/**
	 * @param array $input key=>value vars to overwrite map file %placeholders%
	 * @param string $mapFilePath map file path
	 * @param string $outputFormat
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws \Symfony\Component\Yaml\Exception\ParseException;
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public static function map( $input, $mapFilePath, $outputFormat = null ) {
		try {
			$mapper = new static;
			$yaml = $mapper->loadMapFile( $mapFilePath );
			$map = $mapper->convertYamlMapToArray( $yaml );
			$output = $mapper->translatePlaceholdersToInput( $map, $input );
			if ( isset( $outputFormat ) && $outputFormat != static::FORMAT_ARRAY ) {
				$output = $mapper->formatOutput( $output, $outputFormat );
			}

			return $output;

		} catch ( SmashPigException $exception ) {
			Logger::error(
				'Unable to load map file',
				$exception->getMessage(),
				$exception
			);
			throw $exception;

		} catch ( ParseException $exception ) {
			Logger::error(
				'Unable to parse map file',
				$exception->getMessage(),
				$exception
			);
			throw $exception;

		} catch ( \Exception $exception ) {
			Logger::error(
				'Unable to carry out map process with supplied arguments',
				$exception->getMessage(),
				$exception
			);
			throw $exception;
		}
	}

	/**
	 * Convert YAML string to array
	 *
	 * @param string $yaml
	 *
	 * @return array
	 */
	protected function convertYamlMapToArray( $yaml ) {
		return Yaml::parse( $yaml, true );
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
		$fullMapFilePath = __DIR__ . "/../" . $mapFilePath;
		if ( !is_file( $fullMapFilePath ) ) {
			throw new SmashPigException( "File $fullMapFilePath does not exist." );
		}

		if ( !is_readable( $fullMapFilePath ) ) {
			throw new SmashPigException( "File $fullMapFilePath cannot be read." );
		}

		return file_get_contents( $fullMapFilePath );
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

		// walk through map array setting %placeholder% values as either the
		// corresponding input value if set, or null if no matching input value set.
		array_walk_recursive( $map, function ( &$value ) use ( $input ) {
			if ( preg_match( "/%[^%]+%/", $value ) === 1 ) {
				$value = array_key_exists( $value, $input ) ? $input[$value] : null;
			}
		} );

		return $map;
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
				throw new \InvalidArgumentException( "Invalid Mapper output format supplied: " . $format );
		}
	}
}
