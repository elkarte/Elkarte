<?php
/**
 * Copyright 2022 Wikimedia Foundation
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @file
 * @license Apache-2.0
 * @license MIT
 * @license GPL-2.0-or-later
 * @license LGPL-2.1-or-later
 */

namespace Wikimedia\Minify;

/**
 * The base class for stateful minifying without source map support.
 *
 * Some stub mutator methods for source map support are provided for
 * the convenience of callers switching between source map and plain mode.
 */
abstract class MinifierState {
	/** @var string[] The accumulated data for the source map sources line */
	protected $sources = [];

	/** @var array The accumulated data for the source map sourcesContent line */
	protected $sourcesContent = [];

	/** @var string The accumulated minified output */
	protected $minifiedOutput = '';

	/** @var string|null The value for the "file" key in the source map */
	protected $outputFile;

	/** @var string|null The value for the "sourceRoot" key in the source map */
	protected $sourceRoot;

	/** @var callable|null */
	protected $onError;

	/**
	 * Set the name of the output file, to be given as the "file" key.
	 *
	 * @param string $file
	 * @return $this
	 */
	public function outputFile( string $file ) {
		$this->outputFile = $file;
		return $this;
	}

	/**
	 * Set the source root. The spec says this will be merely "prepended" to
	 * the source names, not resolved as a relative URL, so it should probably
	 * have a trailing slash.
	 *
	 * @param string $url
	 * @return $this
	 */
	public function sourceRoot( string $url ) {
		$this->sourceRoot = $url;
		return $this;
	}

	/**
	 * Minify a source file and collect the output and mappings data.
	 *
	 * @param string $url The name of the input file. Possibly a URL relative
	 *   to the source root.
	 * @param string $source The input source text.
	 * @param bool $bundle Whether to add the source text to sourcesContent
	 * @return $this
	 */
	public function addSourceFile( string $url, string $source, bool $bundle = false ) {
		$this->minifiedOutput .= $this->minify( $source );
		return $this;
	}

	/**
	 * Register an error callback
	 *
	 * JavaScriptMinifier assumes all input has previously been validated
	 * by a linter (such as Peast or ESLint) and not attempt to validate
	 * the syntax. Any new or unknown syntax is generally silently preserved
	 * in the output.
	 *
	 * Certain kinds of syntax error are nonetheless detected and can
	 * be logged via this error callback to aid in debugging.
	 *
	 * The error callback may be invoked during MinifierState::addSourceFile
	 * (via JavaScriptMinifier::minify), and is passed a ParseError object
	 * as its only parameter.
	 *
	 * Example:
	 *
	 *   JavaScriptMinifier::createMinifier()
	 *     ->setErrorHandler( static function ( $error ) {
	 *         trigger_error( $error->getMessage() );
	 *     } )
	 *     ->addSourceFile( 'file.js', $source )
	 *     ->getMinifiedOutput();
	 *
	 * @param callable $onError
	 * @return $this
	 */
	public function setErrorHandler( $onError ) {
		$this->onError = $onError;
		return $this;
	}

	/**
	 * Minify a string
	 *
	 * @param string $source
	 * @return string
	 */
	abstract protected function minify( string $source ): string;

	/**
	 * Add a string to the output without any minification or source mapping.
	 *
	 * @param string $output
	 * @return $this
	 */
	public function addOutput( string $output ) {
		$this->minifiedOutput .= $output;
		return $this;
	}

	/**
	 * Add a line break to the output if the output is non-empty and does not
	 * end in a line break.
	 *
	 * @return $this
	 */
	public function ensureNewline() {
		if ( $this->minifiedOutput !== '' && !str_ends_with( $this->minifiedOutput, "\n" ) ) {
			$this->addOutput( "\n" );
		}
		return $this;
	}

	/**
	 * Get the minified output.
	 *
	 * @return string
	 */
	public function getMinifiedOutput() {
		return $this->minifiedOutput;
	}
}
