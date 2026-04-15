# MC-EMS Premium - API Reference

## AJAX Endpoints

Tutti gli endpoint sono disponibili tramite `mcems_premium.ajax_url` con nonce `mcems_premium.nonce`.

---

## Prenotazioni Avanzate

### 1. Ricerca Prenotazioni

**Endpoint:** `mcems_premium_search_bookings`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_search_bookings',
  nonce: mcems_premium.nonce,
  filters: {
    date: '2026-03-13',           // Opzionale
    course: 5,                     // Opzionale (ID corso)
    candidate_search: 'Mario',     // Opzionale
    status: 'active',              // Opzionale: active, pending, cancelled
    page: 1,                       // Opzionale (default: 1)
    per_page: 20,                  // Opzionale (default: 20)
    search: 'candidato'            // Opzionale (ricerca globale)
  }
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": {
    "bookings": [
      {
        "id": 1,
        "candidate_name": "Mario Rossi",
        "candidate_email": "mario@example.com",
        "course_name": "PHP Avanzato",
        "session_date": "2026-03-15",
        "status": "active",
        "created_at": "2026-03-13 10:30:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_items": 95,
      "per_page": 20,
      "prev_page": null,
      "next_page": 2
    }
  }
}
```

**Risposta Error:**
```json
{
  "success": false,
  "data": "Errore nella ricerca prenotazioni"
}
```

---

### 2. Esportazione CSV

**Endpoint:** `mcems_premium_export_bookings`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_export_bookings',
  nonce: mcems_premium.nonce,
  filters: {
    date: '2026-03-13',
    course: 5,
    status: 'active'
    // ... stessi filtri di ricerca
  }
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": "ID,Candidato,Email,Corso,Data,Status\n1,Mario Rossi,mario@example.com,PHP Avanzato,2026-03-15,active\n..."
}
```

**Risposta Error:**
```json
{
  "success": false,
  "data": "Errore nell'export"
}
```

---

## Sessioni Speciali

### 3. Ricerca Candidati

**Endpoint:** `mcems_premium_search_candidates`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_search_candidates',
  nonce: mcems_premium.nonce,
  query: 'Mario'  // Query di ricerca (min 2 caratteri)
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Mario Rossi",
      "email": "mario@example.com",
      "phone": "333-1234567",
      "enrollments": 5
    },
    {
      "id": 2,
      "name": "Mario Bianchi",
      "email": "mario.b@example.com",
      "phone": "333-7654321",
      "enrollments": 3
    }
  ]
}
```

---

### 4. Aggiungi Sessione Speciale

**Endpoint:** `mcems_premium_add_special_session`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_add_special_session',
  nonce: mcems_premium.nonce,
  candidate_id: 1,
  session_id: 5,
  notes: 'Candidato con esigenze di accessibilità'
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": {
    "special_session_id": 42,
    "candidate_id": 1,
    "session_id": 5,
    "status": "active",
    "created_at": "2026-03-13 10:30:00"
  }
}
```

**Risposta Error:**
```json
{
  "success": false,
  "data": "Sessione già assegnata"
}
```

---

## Reports & Dashboard

### 5. Dati Trend Prenotazioni

**Endpoint:** `mcems_premium_get_trend_data`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_get_trend_data',
  nonce: mcems_premium.nonce,
  date_from: '2026-03-01',      // Opzionale
  date_to: '2026-03-31'         // Opzionale
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": {
    "labels": ["Lun", "Mar", "Mer", "Gio", "Ven", "Sab", "Dom"],
    "values": [15, 18, 12, 20, 25, 8, 5]
  }
}
```

---

### 6. Dati Prenotazioni per Sessione

**Endpoint:** `mcems_premium_get_session_data`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_get_session_data',
  nonce: mcems_premium.nonce,
  course_id: 5  // Opzionale
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": {
    "labels": ["Sessione 1", "Sessione 2", "Sessione 3", "Sessione 4"],
    "values": [25, 18, 22, 15]
  }
}
```

---

### 7. Dati Distribuzione Corsi

**Endpoint:** `mcems_premium_get_course_data`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_get_course_data',
  nonce: mcems_premium.nonce
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": {
    "labels": ["PHP", "JavaScript", "Python", "React", "Laravel"],
    "values": [120, 95, 87, 65, 42]
  }
}
```

---

### 8. Dati Occupazione Sessioni

**Endpoint:** `mcems_premium_get_occupancy_data`

**Metodo:** POST

**Parametri:**
```javascript
{
  action: 'mcems_premium_get_occupancy_data',
  nonce: mcems_premium.nonce
}
```

**Risposta Success:**
```json
{
  "success": true,
  "data": {
    "labels": ["Sessione 1", "Sessione 2", "Sessione 3", "Sessione 4"],
    "values": [85, 72, 90, 65]
  }
}
```

---

## JavaScript API

### MCEMSPremium.BookingsList

```javascript
// Inizializza
MCEMSPremium.BookingsList.init();

// Ricerca prenotazioni
MCEMSPremium.BookingsList.searchBookings();

// Resetta filtri
MCEMSPremium.BookingsList.resetFilters();

// Esporta CSV
MCEMSPremium.BookingsList.exportToCSV();

// Ordina tabella
MCEMSPremium.BookingsList.sortTable('column_name');

// Carica pagina
MCEMSPremium.BookingsList.loadPage(2);

// Ricerca live
MCEMSPremium.BookingsList.liveSearch('query');

// Ottieni filtri correnti
const filters = MCEMSPremium.BookingsList.getFilters();
```

---

### MCEMSPremium.SpecialSessions

```javascript
// Inizializza
MCEMSPremium.SpecialSessions.init();

// Ricerca candidati
MCEMSPremium.SpecialSessions.searchCandidates('Mario');

// Seleziona candidato
MCEMSPremium.SpecialSessions.selectCandidate(1, 'Mario Rossi');

// Aggiungi sessione speciale
MCEMSPremium.SpecialSessions.addSpecialSession();
```

---

### MCEMSPremium.Modal

```javascript
// Apri modal
MCEMSPremium.Modal.open('modal-id');

// Chiudi modal
MCEMSPremium.Modal.close('modal-id');

// Inizializza
MCEMSPremium.Modal.init();
```

---

### MCEMSCharts

```javascript
// Inizializza tutti i grafici
MCEMSCharts.initializeAll();

// Grafico trend
MCEMSCharts.trendChart('canvas-id', data);

// Grafico sessioni
MCEMSCharts.sessionChart('canvas-id', data);

// Grafico distribuzione corsi
MCEMSCharts.courseDistributionChart('canvas-id', data);

// Grafico occupazione
MCEMSCharts.occupancyChart('canvas-id', data);

// Grafico prenotazioni per corso
MCEMSCharts.courseBookingsChart('canvas-id', data);

// Grafico status
MCEMSCharts.statusChart('canvas-id', data);
```

---

## PHP Hooks

### Actions

```php
// Quando viene cercata una prenotazione
do_action('mcems_premium_bookings_searched', $filters, $results);

// Quando viene esportato CSV
do_action('mcems_premium_booking_exported', $csv_data, $filters);

// Quando viene aggiunta sessione speciale
do_action('mcems_premium_special_session_added', $session_id, $candidate_id);

// Quando vengono generate statistiche
do_action('mcems_premium_reports_generated', $report_data);
```

### Filters

```php
// Modifica lista prenotazioni
$bookings = apply_filters(
  'mcems_premium_bookings_list',
  $bookings,
  $filters
);

// Modifica CSV export
$csv = apply_filters(
  'mcems_premium_export_csv',
  $csv,
  $bookings
);

// Modifica dati report
$reports = apply_filters(
  'mcems_premium_report_data',
  $reports
);

// Modifica risultati ricerca candidati
$candidates = apply_filters(
  'mcems_premium_search_candidates',
  $candidates,
  $query
);
```

---

## Gestione Errori

### Errori Comuni

**401 Unauthorized**
```json
{
  "success": false,
  "data": "Nonce non valido"
}
```

**403 Forbidden**
```json
{
  "success": false,
  "data": "Permessi insufficienti"
}
```

**400 Bad Request**
```json
{
  "success": false,
  "data": "Parametri mancanti o non validi"
}
```

**500 Internal Server Error**
```json
{
  "success": false,
  "data": "Errore del server"
}
```

---

## Rate Limiting

- Massimo 100 richieste per minuto per IP
- Massimo 1000 richieste per ora per utente
- Timeout: 30 secondi

---

## Autenticazione

Tutte le richieste richiedono:
- Nonce WordPress valido
- Utente loggato con permessi appropriati
- Cookie di sessione valido

---

## Versione API

Versione attuale: **1.0**

Endpoint: `/wp-admin/admin-ajax.php`

---

## Changelog

### v1.0 (2026-03-13)
- ✅ API prenotazioni avanzata
- ✅ API sessioni speciali
- ✅ API reports e grafici
- ✅ Support per CORS
- ✅ Rate limiting

---

## Supporto

Per domande sull'API:
- Email: api@mambacoding.com
- Documentazione: https://docs.mambacoding.com/premium/api
- Forum: https://community.mambacoding.com
