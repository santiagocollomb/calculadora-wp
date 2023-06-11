<?php
/*
Plugin Name: Calculadora de Intereses
Description: Calculadora de intereses con sistema de amortización francés.
Version: 1.0
Author: Tu Nombre
*/

// Crea una tabla en la base de datos para almacenar los intereses
function crear_tabla_intereses() {
    global $wpdb;
    $tabla_intereses = $wpdb->prefix . 'intereses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla_intereses (
        id INT NOT NULL AUTO_INCREMENT,
        tasa_interes DECIMAL(5,2) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'crear_tabla_intereses' );

// Agrega una página de configuración en el panel de administración
function agregar_pagina_calculadora() {
    add_menu_page(
        'Calculadora de Intereses',
        'Calculadora',
        'manage_options',
        'calculadora-intereses',
        'mostrar_pagina_calculadora',
        'dashicons-calculator',
        10
    );
}
add_action( 'admin_menu', 'agregar_pagina_calculadora' );

// Muestra la página de configuración en el panel de administración
function mostrar_pagina_calculadora() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Guarda los cambios en la base de datos si se ha enviado el formulario
    if ( isset( $_POST['guardar_interes'] ) ) {
        $tasa_interes = $_POST['tasa_interes'];
        actualizar_tasa_interes( $tasa_interes );
    }

    // Obtiene el valor actual de la tasa de interés
    $tasa_interes = obtener_tasa_interes();

    // Calcula el resultado si se ha enviado el formulario de cálculo
    $resultado = '';
    if ( isset( $_POST['calcular'] ) ) {
        $monto_total = floatval( $_POST['monto_total'] );
        $cuotas = intval( $_POST['cuotas'] );
        $resultado = calcular_amortizacion_francesa( $monto_total, $cuotas, $tasa_interes );
    }

    ?>
    <div class="wrap">
        <h1>Calculadora de Intereses</h1>
        <form method="post" action="">
            <label for="tasa_interes">Tasa de Interés:</label>
            <input type="text" name="tasa_interes" id="tasa_interes" value="<?php echo $tasa_interes; ?>" />
            <p class="submit">
                <input type="submit" name="guardar_interes" class="button button-primary" value="Guardar Cambios" />
            </p>
        </form>

        <?php if ( ! empty( $resultado ) ) : ?>
            <h3>Resultado:</h3>
            <?php echo $resultado; ?>
        <?php endif; ?>
    </div>
    <?php
}

// Calcula la amortización francesa y devuelve el resultado formateado
function calcular_amortizacion_francesa( $monto_total, $cuotas, $tasa_interes ) {
    // Cálculos del sistema de amortización francés
    $interes_mensual = $tasa_interes / 12 / 100;
    $factor = (1 + $interes_mensual) ** $cuotas;
    $cuota_mensual = $monto_total * ($interes_mensual * $factor) / ($factor - 1);

    // Construye la tabla con los detalles de la amortización
    $tabla_amortizacion = "<h3>Tabla de Amortización:</h3>";
    $tabla_amortizacion .= "<table>";
    $tabla_amortizacion .= "<tr><th>Número de cuota</th><th>Monto de cuota</th><th>Monto de interés</th><th>Reducción de capital</th><th>Capital adeudado</th></tr>";

    $capital_adeudado = $monto_total;
    for ($i = 1; $i <= $cuotas; $i++) {
        $monto_interes = $capital_adeudado * $interes_mensual;
        $reduccion_capital = $cuota_mensual - $monto_interes;
        $capital_adeudado -= $reduccion_capital;

        $tabla_amortizacion .= "<tr>";
        $tabla_amortizacion .= "<td>$i</td>";
        $tabla_amortizacion .= "<td>" . number_format( $cuota_mensual, 2 ) . "</td>";
        $tabla_amortizacion .= "<td>" . number_format( $monto_interes, 2 ) . "</td>";
        $tabla_amortizacion .= "<td>" . number_format( $reduccion_capital, 2 ) . "</td>";
        $tabla_amortizacion .= "<td>" . number_format( $capital_adeudado, 2 ) . "</td>";
        $tabla_amortizacion .= "</tr>";
    }

    $tabla_amortizacion .= "</table>";
    $resultado = $tabla_amortizacion;

    return $resultado;
}

// Actualiza el valor de la tasa de interés en la base de datos
function actualizar_tasa_interes( $tasa_interes ) {
    global $wpdb;
    $tabla_intereses = $wpdb->prefix . 'intereses';

    $existente = $wpdb->get_var( "SELECT id FROM $tabla_intereses LIMIT 1" );

    if ( $existente ) {
        $wpdb->update(
            $tabla_intereses,
            array( 'tasa_interes' => $tasa_interes ),
            array( 'id' => $existente )
        );
    } else {
        $wpdb->insert(
            $tabla_intereses,
            array( 'tasa_interes' => $tasa_interes )
        );
    }
}

// Obtiene el valor actual de la tasa de interés desde la base de datos
function obtener_tasa_interes() {
    global $wpdb;
    $tabla_intereses = $wpdb->prefix . 'intereses';

    $tasa_interes = $wpdb->get_var( "SELECT tasa_interes FROM $tabla_intereses WHERE id = 1" );

    if ( $tasa_interes === null ) {
        // Valor predeterminado si no hay registros en la base de datos
        $tasa_interes = 0.0;
    }

    return $tasa_interes;
}

// Registra los estilos y scripts necesarios
function registrar_estilos_scripts() {
    wp_enqueue_style( 'calculadora-estilos', plugin_dir_url( __FILE__ ) . 'estilos.css' );
}
add_action( 'admin_enqueue_scripts', 'registrar_estilos_scripts' );

// Registra el shortcode [calculadora_intereses]
function registrar_shortcode_calculadora_intereses() {
    add_shortcode( 'calculadora_intereses', 'mostrar_calculadora_intereses' );
}
add_action( 'init', 'registrar_shortcode_calculadora_intereses' );

// Muestra la calculadora de intereses en el contenido del shortcode [calculadora_intereses]
function mostrar_calculadora_intereses() {
    ob_start();
    ?>
    <div class="calculadora-intereses">
        <form method="post" action="">
            <div class="inputs">
                <div class="input-container">
                    <label for="monto_total">Monto a Financiar:</label>
                    <div class="form-group">
                        <span>$</span>
                        <input class="form-field" type="text" name="monto_total" id="monto_total" />
                    </div>
                </div>
                <div class="input-container">
                    <label for="cuotas">Cantidad de Cuotas:</label>
                    <div class="form-group no-span">
                        <input class="form-field" type="text" name="cuotas" id="cuotas" />
                    </div>
                </div>
                <input type="submit" name="calcular" class="button button-primary" value="Calcular" />
            </div>
        </form>

        <?php if ( isset( $_POST['calcular'] ) ) : ?>
            <?php
            $monto_total = floatval( $_POST['monto_total'] );
            $cuotas = intval( $_POST['cuotas'] );
            $tasa_interes = obtener_tasa_interes();
            $resultado = calcular_amortizacion_francesa( $monto_total, $cuotas, $tasa_interes );
            ?>
            <div class="resultado"><?php echo $resultado; ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Registra los estilos personalizados
function registrar_estilos_personalizados() {
    wp_enqueue_style( 'calculadora-estilos', plugin_dir_url( __FILE__ ) . 'estilos.css' );
}
add_action( 'wp_enqueue_scripts', 'registrar_estilos_personalizados' );
