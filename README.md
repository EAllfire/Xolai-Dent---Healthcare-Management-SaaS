<<<<<<< HEAD
# Hospital Angeles - Sitio Web Público

## Descripción
Sitio web público para mostrar los servicios de imagenología del Hospital Angeles a los clientes/pacientes. Este sitio es completamente independiente del sistema interno de administración.

## Estructura
```
public/
├── index.php          # Página principal con modalidades
├── servicios.php      # Página de servicios por modalidad
├── api/
│   ├── modalidades.php # API para obtener modalidades
│   └── servicios.php   # API para obtener servicios
├── .htaccess          # Configuración de servidor
└── README.md          # Este archivo
```

## Funcionalidades

### Página Principal (index.php)
- **Diseño moderno**: Interfaz limpia con diseño Hospital Angeles
- **Navegación por tabs**: Modalidades, Paquetes, Contacto
- **Cards interactivas**: Cada modalidad se muestra en una tarjeta clickeable
- **Vista previa de servicios**: Muestra primeros 3 servicios de cada modalidad
- **Responsive**: Adaptado para móviles y desktop

### Página de Servicios (servicios.php)
- **Listado detallado**: Todos los servicios de una modalidad específica
- **Información completa**: Nombre, descripción, duración, precio
- **Navegación**: Botón para regresar a la página principal
- **Diseño profesional**: Cards con información bien organizada

### APIs (/api/)
- **modalidades.php**: Retorna todas las modalidades con conteo de servicios
- **servicios.php**: Retorna servicios filtrados por modalidad
- **CORS habilitado**: Permite peticiones desde cualquier origen
- **Manejo de errores**: Respuestas JSON estructuradas

## Tecnologías Utilizadas
- **Frontend**: HTML5, CSS3, JavaScript (ES6), Bootstrap 4.6.2
- **Backend**: PHP 7.4+, MySQL
- **Iconos**: Font Awesome 6.0
- **Tipografía**: Google Fonts (Roboto)

## Modalidades Soportadas
- Radiología
- Tomografía  
- Resonancia Magnética
- Ultrasonido
- Mamografía
- Densitometría
- Fluoroscopía
- Angiografía

## Características de Diseño
- **Colores corporativos**: Grises y azules del Hospital Angeles
- **Animaciones suaves**: Transiciones CSS para mejor UX
- **Responsive**: Diseño adaptativo para todos los dispositivos
- **Loading states**: Indicadores de carga mientras se obtienen datos
- **Error handling**: Manejo elegante de errores de conexión

## URLs
- **Página principal**: `/public/index.php`
- **Servicios**: `/public/servicios.php?modalidad=[ID]&nombre=[NOMBRE]`
- **API Modalidades**: `/public/api/modalidades.php`
- **API Servicios**: `/public/api/servicios.php?modalidad_id=[ID]`

## Notas de Implementación
- El sitio está completamente separado del sistema interno
- Utiliza las mismas tablas de base de datos pero solo en modo lectura
- No requiere autenticación
- Optimizado para SEO básico
- Preparado para agregar paquetes en el futuro
=======
# Xolai-Dent---Healthcare-Management-SaaS
A full-stack SaaS platform tailored for dental health professionals, focused on streamlining scheduling, patient records, and clinic management.
>>>>>>> 362ccf390678eb922f909fa2c1adeab26c145859
