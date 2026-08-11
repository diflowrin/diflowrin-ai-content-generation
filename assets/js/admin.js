/**
 * Content Architect — admin UI helpers.
 * Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	// ---- Confirmation links (e.g. Disconnect) ----
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '[data-ca-confirm]' );
		if ( link && ! window.confirm( link.getAttribute( 'data-ca-confirm' ) ) ) {
			event.preventDefault();
		}
	} );

	// ---- Show / hide the API key field ----
	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-ca-toggle]' );
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();

		var input = document.getElementById( trigger.getAttribute( 'data-ca-toggle' ) );
		if ( ! input ) {
			return;
		}

		var isHidden = input.type === 'password';
		input.type = isHidden ? 'text' : 'password';
		trigger.textContent = isHidden ? ( trigger.dataset.hideLabel || 'Hide' ) : ( trigger.dataset.showLabel || 'Show' );
	} );

	// ---- Generate an article ----
	var form = document.getElementById( 'ca-generate-form' );
	if ( ! form || typeof diflowrin_content_generator_data === 'undefined' ) {
		return;
	}

	var btn = document.getElementById( 'ca-generate-btn' );
	var result = document.getElementById( 'ca-result' );

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.textContent = str == null ? '' : String( str );
		return d.innerHTML;
	}

	function setResult( html ) {
		result.innerHTML = html;
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		if ( ! diflowrin_content_generator_data.hasKey ) {
			setResult( '<p class="ca-note ca-note--warn" style="margin:0;">' + esc( diflowrin_content_generator_data.i18n.noKey ) + '</p>' );
			return;
		}

		var topic = ( document.getElementById( 'ca-topic' ).value || '' ).trim();
		var sourceUrl = ( document.getElementById( 'ca-source-url' ).value || '' ).trim();

		// A topic OR a source URL is enough (link-only generation is supported).
		if ( ! topic && ! sourceUrl ) {
			setResult( '<p class="ca-note ca-note--warn" style="margin:0;">' + esc( diflowrin_content_generator_data.i18n.noInput ) + '</p>' );
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', diflowrin_content_generator_data.action );
		body.set( 'nonce', diflowrin_content_generator_data.nonce );
		body.set( 'topic', topic );
		body.set( 'tone', document.getElementById( 'ca-tone' ).value );
		body.set( 'length', document.getElementById( 'ca-length' ).value );
		body.set( 'language', ( document.getElementById( 'ca-language' ).value || 'English' ).trim() );
		body.set( 'source_url', sourceUrl );
		body.set( 'with_image', document.getElementById( 'ca-with-image' ).checked ? '1' : '' );
		body.set( 'image_count', document.getElementById( 'ca-image-count' ).value );
		body.set( 'ai_disclosure', document.getElementById( 'ca-ai-disclosure' ).checked ? '1' : '' );

		var originalLabel = btn.textContent;
		btn.disabled = true;
		btn.textContent = diflowrin_content_generator_data.i18n.generating;
		setResult(
			'<div class="ca-skeleton"><span class="ca-skeleton__bar" style="width:70%"></span>' +
			'<span class="ca-skeleton__bar"></span><span class="ca-skeleton__bar" style="width:85%"></span>' +
			'<span class="ca-skeleton__bar" style="width:60%"></span></div>'
		);

		fetch( diflowrin_content_generator_data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var msg = json && json.data && json.data.message ? json.data.message : diflowrin_content_generator_data.i18n.failed;
					setResult( '<p class="ca-note ca-note--warn" style="margin:0;">' + esc( msg ) + '</p>' );
					return;
				}

				var d = json.data;
				var html = '<p class="ca-result__title">' + esc( d.title ) + '</p>';
				html += '<p class="ca-result__meta">';
				html += esc( d.image ? diflowrin_content_generator_data.i18n.draftCreatedImage : diflowrin_content_generator_data.i18n.draftCreated );
				html += '</p>';
				// Both can apply at once: page content as primary source, Sonar research as enrichment.
				if ( d.source_used && sourceUrl ) {
					html += '<p class="ca-note ca-note--ok">' + esc( diflowrin_content_generator_data.i18n.sourceUsed ) + '</p>';
				}
				if ( d.research_used ) {
					html += '<p class="ca-note ca-note--ok">' + esc( diflowrin_content_generator_data.i18n.researchUsed ) + '</p>';
				}
				if ( d.source_error ) {
					var skippedLabel = sourceUrl ? diflowrin_content_generator_data.i18n.sourceSkipped : diflowrin_content_generator_data.i18n.researchSkipped;
					html += '<p class="ca-note ca-note--warn">' + esc( skippedLabel + ' ' + d.source_error ) + '</p>';
				}
				if ( d.image_error ) {
					html += '<p class="ca-note ca-note--warn">' + esc( diflowrin_content_generator_data.i18n.imageSkipped + ' ' + d.image_error ) + '</p>';
				}
				if ( d.ai_disclosure ) {
					html += '<p class="ca-note ca-note--ok">' + esc( diflowrin_content_generator_data.i18n.disclosureAdded ) + '</p>';
				}
				if ( d.images_message ) {
					html += '<p class="ca-note ca-note--ok">' + esc( d.images_message ) + '</p>';
				}
				if ( d.images_error ) {
					html += '<p class="ca-note ca-note--warn">' + esc( diflowrin_content_generator_data.i18n.imagesSkipped + ' ' + d.images_error ) + '</p>';
				}
				if ( d.edit_url ) {
					html += '<a class="ca-btn ca-btn--ghost" href="' + esc( d.edit_url ) + '">' + esc( diflowrin_content_generator_data.i18n.editDraft ) + '</a>';
				}
				// The featured image is the post thumbnail, not part of the body, so
				// the content preview below can never show it. Without this the user
				// has no way to tell whether a cover was actually produced.
				if ( d.image_url ) {
					html += '<figure class="ca-result__featured">' +
						'<img src="' + esc( d.image_url ) + '" alt="" />' +
						'<figcaption>' + esc( diflowrin_content_generator_data.i18n.featuredImage ) + '</figcaption>' +
						'</figure>';
				}
				// d.content is wp_kses_post-sanitised server-side, so it is safe to render as HTML.
				if ( d.content ) {
					html += '<div class="ca-article-preview">' + d.content + '</div>';
				}
				setResult( html );
			} )
			.catch( function () {
				setResult( '<p class="ca-note ca-note--warn" style="margin:0;">' + esc( diflowrin_content_generator_data.i18n.failed ) + '</p>' );
			} )
			.finally( function () {
				btn.disabled = false;
				btn.textContent = originalLabel;
			} );
	} );
} )();
