# MC-EMS Premium - Features Guide

## Panoramica

MC-EMS Premium offre funzionalità avanzate per la gestione completa di corsi e prenotazioni con limiti illimitati e controlli granulari.

## Features Premium

### 1. Lista Prenotazioni Avanzata

#### Descrizione
Sistema di gestione prenotazioni con filtri avanzati, ricerca real-time e export dati.

#### Caratteristiche
- **Filtri avanzati**: Data, corso, candidato, status
- **Ricerca real-time**: Ricerca istantanea con debounce
- **Ordinamento colonne**: Clicca su qualsiasi intestazione tabella
- **Paginazione**: Navigazione tra pagine di risultati
- **Export CSV**: Scarica dati con filtri applicati
- **Responsive design**: Funziona su mobile e desktop

#### Come usare
```html
<div class="mcems-bookings-list-premium">
  <div class="filters">
    <div class="filter-group">
      <label>Data</label>
      <input type="date" name="date">
    </div>
    <div class="filter-group">
      <label>Corso</label>
      <select name="course">
        <option value="">Seleziona corso</option>
      </select>
    </div>
    <div class="filter-buttons">
      <button class="btn-search">Cerca</button>
      <button class="btn-reset">Ripristina</button>
      <button class="btn-export">Esporta CSV</button>
    </div>
  </div>
  
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th data-column="id">ID</th>
          <th data-column="name">Candidato</th>
          <th data-column="course">Corso</th>
          <th data-column="date">Data</th>
          <th data-column="status">Status</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
  
  <div class="pagination"></div>
</div>
```

#### JavaScript Initialization
```javascript
jQuery(document).ready(function() {
  MCEMSPremium.BookingsList.init();
});
```

---

### 2. Sessioni Accessibilità

#### Descrizione
Gestione di sessioni speciali per candidati con esigenze particolari o accessibilità.

#### Caratteristiche
- **Ricerca candidati**: Trova candidati per nome/email
- **Assegnazione sessioni**: Assegna candidati a sessioni speciali
- **Note personalizzate**: Aggiungi note specifiche
- **Stato tracciamento**: Monitora stato di ciascuna sessione speciale

#### Come usare
```html
<div class="mcems-special-sessions">
  <h3>Sessioni Accessibilità</h3>
  
  <div class="special-session-form">
    <div class="form-group">
      <label>Cerca Candidato</label>
      <input type="text" name="candidate_search" placeholder="Nome o email">
      <div class="candidate-results"></div>
      <input type="hidden" name="candidate_id">
    </div>
    
    <div class="form-group">
      <label>Sessione Speciale</label>
      <select name="session_id">
        <option value="">Seleziona sessione</option>
      </select>
    </div>
    
    <div class="form-group">
      <label>Note</label>
      <textarea name="notes" placeholder="Note sulla sessione speciale"></textarea>
    </div>
    
    <button class="btn-add-special-session">Aggiungi Sessione Speciale</button>
  </div>
</div>
```

#### JavaScript Initialization
```javascript
jQuery(document).ready(function() {
  MCEMSPremium.SpecialSessions.init();
});
```

---

### 3. Reports & Dashboard

#### Descrizione
Dashboard interattiva con grafici e statistiche in tempo reale.

#### Caratteristiche
- **Trend prenotazioni**: Grafico lineare con trend
- **Prenotazioni per sessione**: Grafico a barre orizzontale
- **Distribuzione corsi**: Grafico a ciambella (doughnut)
- **Occupazione sessioni**: Grafico radar con percentuali
- **Statistiche riepilogative**: Numeri chiave

#### Come usare
```html
<div class="mcems-reports-dashboard">
  <!-- Cards statistiche -->
  <div class="reports-container">
    <div class="report-card">
      <h4>Prenotazioni Totali</h4>
      <div class="stat-number">245</div>
      <div class="stat-label">Nel periodo selezionato</div>
    </div>
    
    <div class="report-card">
      <h4>Occupazione Media</h4>
      <div class="stat-number">78%</div>
      <div class="stat-label">Sessioni occupate</div>
    </div>
    
    <div class="report-card">
      <h4>Corsi Attivi</h4>
      <div class="stat-number">12</div>
      <div class="stat-label">Corsi in calendario</div>
    </div>
  </div>
  
  <!-- Grafici -->
  <div class="chart-container">
    <canvas id="mcems-trend-chart"></canvas>
  </div>
  
  <div class="chart-container">
    <canvas id="mcems-session-chart"></canvas>
  </div>
  
  <div class="chart-container">
    <canvas id="mcems-course-chart"></canvas>
  </div>
  
  <div class="chart-container">
    <canvas id="mcems-occupancy-chart"></canvas>
  </div>
</div>
```

#### JavaScript Initialization
```javascript
jQuery(document).ready(function() {
  MCEMSCharts.initializeAll();
});
```

---

### 4. Limiti Illimitati

#### Descrizione
Rimuove tutti i limiti sulla creazione di prenotazioni, corsi e sessioni.

#### Caratteristiche
- ✅ Prenotazioni illimitate per sessione
- ✅ Sessioni illimitate per corso
- ✅ Corsi illimitati
- ✅ Candidati illimitati
- ✅ Nessun limite di data

#### Come funziona
La classe `MCEMS_Unlimited_Limits` sovrascrive i limiti del plugin base:

```php
// Nel file mc-ems-premium.php
require_once MCEMS_PREMIUM_DIR . '/includes/class-mcems-unlimited-limits.php';

// La classe gestisce:
// - Validazione prenotazioni
// - Controllo slot disponibili
// - Controlli di capacità
```

---

## Styling Premium

### CSS Classes

#### Contenitori
- `.mcems-bookings-list-premium` - Contenitore lista prenotazioni
- `.mcems-special-sessions` - Contenitore sessioni speciali
- `.mcems-reports-dashboard` - Contenitore dashboard
- `.chart-container` - Contenitore grafici

#### Componenti
- `.filters` - Sezione filtri
- `.filter-group` - Singolo filtro
- `.table-wrapper` - Wrapper tabella
- `.pagination` - Paginazione
- `.report-card` - Card statistiche
- `.mcems-modal-overlay` - Overlay modal
- `.mcems-modal` - Modal dialog

#### Utility
- `.text-muted` - Testo attenuato
- `.text-success` - Testo verde (successo)
- `.text-warning` - Testo giallo (avvertenza)
- `.text-danger` - Testo rosso (errore)
- `.text-info` - Testo blu (informazione)
- `.mt-20` - Margine superiore 20px
- `.mb-20` - Margine inferiore 20px
- `.hidden` - Nascondere elemento
- `.loading` - Stato caricamento

---

## Dark Mode

Tutte le features premium supportano automaticamente dark mode. Il CSS utilizza `@media (prefers-color-scheme: dark)` per adattarsi alle preferenze del sistema.

---

## Responsive Design

Le feature premium sono completamente responsive:

- **Desktop** (>768px): Layout full con tabelle complete
- **Tablet** (768px): Layout ottimizzato con tabelle scrollabili
- **Mobile** (<768px): Layout mobile con modal fullscreen

---

## Hooks e Filters

### Hooks d'Azione (Actions)

```php
do_action('mcems_premium_bookings_searched', $filters, $results);
do_action('mcems_premium_booking_exported', $csv_data);
do_action('mcems_premium_special_session_added', $session_id);
do_action('mcems_premium_reports_generated', $report_data);
```

### Filtri

```php
$bookings = apply_filters('mcems_premium_bookings_list', $bookings, $filters);
$csv = apply_filters('mcems_premium_export_csv', $csv, $bookings);
$reports = apply_filters('mcems_premium_report_data', $reports);
```

---

## Browser Support

- Chrome/Edge: ✅ Completo
- Firefox: ✅ Completo
- Safari: ✅ Completo
- IE11: ⚠️ Supporto limitato (no Promise, no Arrow functions)

---

## Performance

- **Lazy loading**: I grafici si caricano al bisogno
- **Debouncing**: Ricerca limitata a 300ms per ridurre richieste
- **Caching**: I dati vengono cachati localmente
- **Minification**: File CSS e JS minificati in produzione

---

## Risoluzione Problemi

### I grafici non si visualizzano
- Verifica che Chart.js sia caricato
- Controlla la console browser per errori
- Assicurati che i dati AJAX siano disponibili

### La ricerca non funziona
- Verifica che jQuery sia caricato
- Controlla che l'endpoint AJAX sia accessibile
- Verifica i permessi dell'utente

### Esportazione CSV non funziona
- Controlla i permessi di file
- Verifica che il server supporti il download
- Controlla il limite di memoria PHP

---

## FAQ

**Q: Posso personalizzare i colori?**
A: Sì, modifica le variabili CSS in `assets/css/premium.css`

**Q: Come aggiungo nuovi filtri?**
A: Modifica il template HTML e aggiungi il filtro nel JavaScript

**Q: I dati vengono salvati?**
A: Dipende dalle vostre implementazioni degli endpoint AJAX

---

## Contatti e Supporto

Per supporto: https://mambacoding.com
Email: info@mambacoding.com
