<?php

namespace SmashPig\PaymentProviders\Chariot;

class UnknownPathCollector {

	/**
	 * @var array<string,array<string,mixed>>
	 */
	private array $unknowns = [];

	/**
	 * @param array $payload
	 * @param array $knownPaths
	 */
	public function scan( array $payload, array $knownPaths ): void {
		$this->scanValue( $payload, '', $knownPaths );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function getUnknowns(): array {
		ksort( $this->unknowns );
		return $this->unknowns;
	}

	/**
	 * @param mixed $value
	 * @param string $path
	 * @param array $knownPaths
	 */
	private function scanValue( $value, string $path, array $knownPaths ): void {
		if ( is_array( $value ) ) {
			if ( $this->isListArray( $value ) ) {
				$listPath = $path === '' ? '[]' : $path;
				if ( !in_array( $listPath, $knownPaths, true ) ) {
					$this->noteUnknownPath( $listPath, $value[0] ?? null );
				}

				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						foreach ( $item as $key => $itemValue ) {
							$childPath = $listPath . '[].' . $key;
							$this->scanValue( $itemValue, $childPath, $knownPaths );
						}
					}
				}

				return;
			}

			if ( $path !== '' && !in_array( $path, $knownPaths, true ) ) {
				$this->noteUnknownPath( $path, $value );
			}

			foreach ( $value as $key => $child ) {
				$childPath = $path === '' ? (string)$key : $path . '.' . $key;
				$this->scanValue( $child, $childPath, $knownPaths );
			}

			return;
		}

		if ( $path !== '' && !in_array( $path, $knownPaths, true ) ) {
			$this->noteUnknownPath( $path, $value );
		}
	}

	/**
	 * @param string $path
	 * @param mixed $sample
	 */
	private function noteUnknownPath( string $path, $sample ): void {
		if ( !isset( $this->unknowns[$path] ) ) {
			$this->unknowns[$path] = [
				'path' => $path,
				'count' => 0,
				'sample' => $this->sampleValue( $sample ),
			];
		}

		$this->unknowns[$path]['count']++;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function sampleValue( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( $value === null ) {
			return null;
		}

		return (string)$value;
	}

	private function isListArray( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

}
