<?php
/**
 * Encuesta "Chequeo de método RRHH" – envío de resultados por email.
 * Pegar en el plugin "Code Snippets" (Ejecutar en: todo el sitio) o en functions.php del tema hijo.
 * El destinatario está fijo acá (no viene del navegador), así nadie puede usarlo para mandar mails a otros.
 */
add_action( 'wp_ajax_wl_encuesta', 'wl_encuesta_enviar' );
add_action( 'wp_ajax_nopriv_wl_encuesta', 'wl_encuesta_enviar' );

function wl_encuesta_enviar() {
	$destino = 'evs@witlab.com.ar';

	// Anti-spam: campo oculto que sólo completan los bots.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success();
	}

	$d = json_decode( wp_unslash( $_POST['data'] ?? '' ), true );
	if ( ! is_array( $d ) || empty( $d['empresa'] ) ) {
		wp_send_json_error( 'Datos inválidos', 400 );
	}

	$t = function ( $k, $max = 200 ) use ( $d ) {
		return isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $max ) : '';
	};

	$empresa  = $t( 'empresa' );
	$contacto = $t( 'contacto' );
	$quiere   = ! empty( $d['quiere_contacto'] );
	$puntaje  = (int) ( $d['puntaje'] ?? 0 );

	$tipos = array_map( 'sanitize_text_field', array_slice( (array) ( $d['capacitaciones'] ?? array() ), 0, 10 ) );

	$b  = "NUEVO CHEQUEO DE MÉTODO RRHH\n\n";
	$b .= "Empresa: $empresa\n";
	$b .= 'Nombre: ' . $t( 'nombre' ) . "\n";
	$b .= "Contacto: $contacto\n";
	$b .= 'Quiere que lo contacten: ' . ( $quiere ? 'SÍ' : 'No' ) . "\n";
	$b .= 'Rol: ' . $t( 'rol' ) . "\n";
	$b .= 'Rubro: ' . $t( 'rubro' ) . "\n";
	$b .= 'Personas: ' . $t( 'personas' ) . "\n";
	$b .= 'Capacitaciones (24 meses): ' . implode( ', ', $tipos ) . "\n";
	$b .= 'Resultados observados: ' . $t( 'resultados_previos' ) . "\n";
	$b .= 'Plan anual: ' . $t( 'plan_anual' ) . "\n";
	$b .= 'Desafío: ' . ( isset( $d['desafio'] ) ? mb_substr( sanitize_textarea_field( (string) $d['desafio'] ), 0, 500 ) : '' ) . "\n\n";

	$b .= "RESULTADO: $puntaje% – " . $t( 'nivel' ) . "\n";
	foreach ( array_slice( (array) ( $d['etapas'] ?? array() ), 0, 4 ) as $e ) {
		$b .= '  · ' . sanitize_text_field( $e['name'] ?? '' ) . ': ' . (int) ( $e['pct'] ?? 0 ) . "%\n";
	}
	$prios = array_map( 'sanitize_text_field', array_slice( (array) ( $d['prioridades'] ?? array() ), 0, 2 ) );
	$b .= 'Prioridades sugeridas: ' . implode( ' / ', $prios ) . "\n\n";

	$b .= "RESPUESTAS\n";
	foreach ( array_slice( (array) ( $d['respuestas'] ?? array() ), 0, 12 ) as $i => $r ) {
		$b .= ( $i + 1 ) . '. ' . sanitize_text_field( $r['pregunta'] ?? '' ) . "\n   → " . sanitize_text_field( $r['respuesta'] ?? '' ) . "\n";
	}

	$asunto  = ( $quiere ? '[QUIERE CONTACTO] ' : '' ) . "Chequeo RRHH: $empresa ($puntaje%)";
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( is_email( $contacto ) ) {
		$headers[] = 'Reply-To: ' . $contacto;
	}

	$ok = wp_mail( $destino, $asunto, $b, $headers );
	$ok ? wp_send_json_success() : wp_send_json_error( 'No se pudo enviar', 500 );
}
