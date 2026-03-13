# MC-EMS Premium - Setup & Installation Guide

## Requisiti di Sistema

### Requisiti Minimi
- **WordPress:** 5.0 o superiore
- **PHP:** 7.0 o superiore
- **MySQL:** 5.6 o superiore
- **MC-EMS Base:** 2.0 o superiore (OBBLIGATORIO)

### Requisiti Consigliati
- **WordPress:** 6.0 o superiore
- **PHP:** 8.0 o superiore
- **MySQL:** 5.7 o superiore
- **Apache/Nginx:** Con mod_rewrite abilitato

### Dipendenze JavaScript
- jQuery (automaticamente caricato da WordPress)
- Chart.js 3.0+ (incluso nel plugin)
- Moment.js (opzionale, per date picker avanzati)

---

## Installazione

### Passo 1: Verifica prerequisiti

Prima di installare MC-EMS Premium, assicurati che:

1. ✅ MC-EMS Base sia installato e attivo
   ```
   WordPress → Plugin → Plugins installati → Cerca "MC-EMS Base"
   ```

2. ✅ WordPress sia aggiornato almeno alla versione 5.0
   ```
   WordPress → Dashboard → Aggiornamenti
   ```

3. ✅ PHP sia versione 7.0 o superiore
   ```
   WordPressAdmin → Strumenti → Integrità del sito → Info sistema
   ```

### Passo 2: Download e Caricamento

**Opzione A: Da WordPress.org (quando disponibile)**
```
1. Vai a WordPress → Plugin → Aggiungi nuovo
2. Cerca "MC-EMS Premium"
3. Clicca "Installa"
4. Clicca "Attiva"
```

**Opzione B: Upload manuale**
```
1. Scarica il file .zip da mambacoding.com
2. Vai a WordPress → Plugin → Aggiungi nuovo
3. Clicca "Carica plugin"
4. Seleziona il file .zip
5. Clicca "Installa"
6. Clicca "Attiva"
```

**Opzione C: Via FTP**
```
1. Estrai il file .zip localmente
2. Carica la cartella "mc-ems-premium" via FTP
   Destinazione: /wp-content/plugins/
3. Vai a WordPress → Plugin → Plugins installati
4. Trova "MC-EMS Premium" e clicca "Attiva"
```

### Passo 3: Verifica Installazione

Dopo l'attivazione:

1. ✅ Controlla che non ci siano errori
   ```
   WordPress → Dashboard → Avvisi (controllare se rossi)
   ```

2. ✅ Verifica che i file siano caricati
   ```
   FTP/File Manager → /wp-content/plugins/mc-ems-premium/
   ```

3. ✅ Controlla il log degli errori
   ```
   /wp-content/debug.log (se wp-debug è abilitato)
   ```

---

## Configurazione Iniziale

### Passo 1: Accesso al Plugin

1. Vai a **WordPress Dashboard**
2. Nel menu laterale, cerca **MC-EMS** (dovrebbe apparire il sottomenu)
3. Clicca su **MC-EMS Premium**

### Passo 2: Impostazioni Base

Nella pagina di setup:

**1. Abilita Features Premium**
```
☐ Abilita Lista Prenotazioni Avanzata
☐ Abilita Sessioni Accessibilità
☐ Abilita Reports e Dashboard
☐ Abilita Limiti Illimitati
```

**2. Configurazione Limiti (se non illimitati)**
```
Massimo prenotazioni per sessione: [____]
Massimo sessioni per corso: [____]
Massimo candidati: [____]
```

**3. Personalizzazione**
```
Colore principale: [#0073aa]
Colore secondario: [#28a745]
Colore pericolo: [#dc3545]
```

**4. Salva Impostazioni**
```
Clicca il pulsante "Salva Impostazioni"
```

### Passo 3: Configurazione Avanzata

Nel file `wp-config.php`, aggiungi (opzionale):

```php
// Abilita debug per MC-EMS Premium
define('MCEMS_PREMIUM_DEBUG', true);

// Imposta livello di logging
define('MCEMS_PREMIUM_LOG_LEVEL', 'info'); // debug, info, warning, error

// Cache timeout per grafici (in secondi)
define('MCEMS_PREMIUM_CHART_CACHE', 3600);

// Massimo record per export
define('MCEMS_PREMIUM_MAX_EXPORT', 10000);
```

---

## Uso delle Features

### Feature 1: Lista Prenotazioni Avanzata

**Come accedere:**
```
WordPress Dashboard → MC-EMS → Prenotazioni Avanzate
```

**Filtri disponibili:**
- Data prenotazione (from/to)
- Corso
- Candidato (ricerca per nome/email)
- Status (Confermato, In sospeso, Cancellato)

**Azioni:**
- 🔍 Ricerca con Enter
- 📊 Ordina per colonna (click su intestazione)
- 📥 Esporta CSV (scarica dati filtrati)
- 📄 Visualizza dettagli prenotazione

---

### Feature 2: Sessioni Accessibilità

**Come accedere:**
```
WordPress Dashboard → MC-EMS → Sessioni Speciali
```

**Come aggiungere sessione:**
```
1. Scrivi il nome del candidato
2. Seleziona dalla lista che appare
3. Scegli la sessione speciale
4. Aggiungi note (opzionale)
5. Clicca "Aggiungi Sessione Speciale"
```

**Come visualizzare:**
```
Le sessioni speciali appariranno nella lista prenotazioni
con badge "Sessione Speciale"
```

---

### Feature 3: Reports & Dashboard

**Come accedere:**
```
WordPress Dashboard → MC-EMS → Reports
```

**Grafici disponibili:**
- 📈 Trend Prenotazioni (ultimi 7 giorni)
- 📊 Prenotazioni per Sessione
- 🍩 Distribuzione Corsi
- 📡 Occupazione Sessioni (%)

**Come personalizzare:**
```
Filtri data: Seleziona periodo
Esporta grafici: Click destro → Salva immagine
```

---

### Feature 4: Limiti Illimitati

**Che cosa cambia:**
```
✅ PRIMA: Max 50 prenotazioni per sessione
✅ DOPO (Premium): Prenotazioni illimitate

✅ PRIMA: Max 20 sessioni per corso
✅ DOPO (Premium): Sessioni illimitate

✅ PRIMA: Max 1000 candidati
✅ DOPO (Premium): Candidati illimitati
```

---

## Troubleshooting

### Problema 1: "MC-EMS Base non trovato"

**Errore:**
```
Il plugin MC-EMS Base non è installato o attivo
```

**Soluzione:**
```
1. Vai a WordPress → Plugin → Plugins installati
2. Cerca "MC-EMS Base"
3. Se non trovato, installalo da WordPress.org
4. Attiva il plugin
5. Torna a MC-EMS Premium e attivalo
```

---

### Problema 2: CSS/JS non si caricano

**Sintomi:**
- Bottoni non stilizzati
- Grafici non funzionano
- Ricerca non risponde

**Soluzione:**
```
1. Svuota cache WordPress (se usi plugin di caching)
2. Esegui hard refresh del browser (Ctrl+Shift+R)
3. Controlla che i file siano in:
   /wp-content/plugins/mc-ems-premium/assets/
4. Se ancora problemi, disabilita altri plugin e riprova
5. Controlla console browser per errori (F12 → Console)
```

---

### Problema 3: Grafici non si visualizzano

**Sintomi:**
- Tela bianca dove dovrebbero esserci grafici
- Errore nella console

**Soluzione:**
```
1. Verifica che Chart.js sia caricato (F12 → Network)
2. Controlla che i dati AJAX arrivino:
   F12 → Network → Cerca "mcems_premium_get_trend_data"
3. Se errore 403, verifica permessi utente
4. Se errore 500, controlla error.log del server
5. Riavvia il browser completamente
```

---

### Problema 4: Export CSV non funziona

**Sintomi:**
- Pulsante "Esporta CSV" non risponde
- Browser scarica file vuoto
- Errore di permessi

**Soluzione:**
```
1. Controlla permessi cartella /wp-content/
2. Assicurati che l'utente abbia permesso "export_bookings"
3. Aumenta il limite di memoria PHP in wp-config.php:
   define('WP_MEMORY_LIMIT', '256M');
   define('WP_MAX_MEMORY_LIMIT', '512M');
4. Riduci il range di date per export (max 3 mesi)
5. Controlla il file php.ini per upload_max_filesize
```

---

### Problema 5: Ricerca non funziona

**Sintomi:**
- Nessun risultato quando si cerca
- Ricerca lenta
- Errore timeout

**Soluzione:**
```
1. Verifica che jQuery sia caricato correttamente
2. Controlla database performance:
   - Numero di prenotazioni eccessivo?
   - Indici database creati?
3. Aumenta timeout AJAX in JavaScript:
   jQuery.ajaxSetup({timeout: 60000}); // 60 secondi
4. Ottimizza query database:
   - Aggiungi indici su colonne filtrate
5. Disabilita temporaneamente plugin di caching
```

---

## Backup e Ripristino

### Backup Dati Premium

**Cosa fare il backup:**
```
1. Database WordPress (contiene tutte le prenotazioni)
2. Plugin files: /wp-content/plugins/mc-ems-premium/
3. Uploads: /wp-content/uploads/mc-ems-premium/
```

**Come eseguire backup:**
```
1. Via WordPress plugin (BackWPup, Updraft Plus)
2. Via cPanel → Backup Wizard
3. Via SSH:
   mysqldump -u user -p database > backup.sql
   tar -czf backup.tar.gz /wp-content/plugins/mc-ems-premium/
```

---

### Ripristino da Backup

**Se qualcosa va male:**
```
1. Disattiva MC-EMS Premium
2. Carica backup del plugin via FTP
3. Ripristina database da backup
4. Attiva il plugin
5. Verifica che funzioni tutto
```

---

## Aggiornamenti

### Come controllare aggiornamenti

```
WordPress Dashboard → Plugin → Plugins installati
Cerca "MC-EMS Premium" → Se disponibile, clicca "Aggiorna"
```

### Prima di aggiornare

```
✅ SEMPRE fare backup
✅ Disabilita plugin di caching
✅ Leggi le note di rilascio
✅ Testa su ambiente di staging prima
```

### Dopo l'aggiornamento

```
✅ Svuota cache browser (Ctrl+Shift+R)
✅ Svuota cache WordPress
✅ Verifica che tutto funzioni
✅ Controlla gli errori nel log
```

---

## FAQ

### D: Posso usare MC-EMS Premium senza MC-EMS Base?
**R:** No, MC-EMS Base è obbligatorio. È il fondamento del plugin.

### D: Quanto costa MC-EMS Premium?
**R:** Visita https://mambacoding.com/prezzi per i dettagli di prezzo.

### D: Posso usarlo su più siti WordPress?
**R:** Dipende dalla licenza. Visita https://mambacoding.com/licenze

### D: Funziona con temi custom?
**R:** Sì, è compatibile con qualsiasi tema WordPress.

### D: Che supporto ricevo?
**R:** Email, chat, documentazione, video tutorial. Visita https://mambacoding.com/supporto

### D: È sicuro?
**R:** Sì, utilizza nonce WordPress e validazione input.

### D: Influisce sulla performance?
**R:** No, utilizza caching e lazy loading per performance ottimale.

### D: Posso ottenere un refund?
**R:** Sì, garanzia 30 giorni soddisfatti o rimborsati.

---

## Contatti e Supporto

📧 **Email:** support@mambacoding.com
💬 **Chat:** https://mambacoding.com/chat
📚 **Documentazione:** https://docs.mambacoding.com
🎥 **Video Tutorial:** https://youtube.com/@mambacoding
🌐 **Sito:** https://mambacoding.com

---

## Changelog

### v2.3.0 (2026-03-13)
- ✅ Lista prenotazioni avanzata con filtri
- ✅ Sessioni accessibilità
- ✅ Dashboard reports con Chart.js
- ✅ Export CSV dinamico
- ✅ Limiti illimitati
- ✅ Responsive design completo
- ✅ Dark mode support
- ✅ Documentazione API completa

### v2.2.0 (2026-01-15)
- ✅ Primo rilascio beta

---

## Licenza

MC-EMS Premium è rilasciato sotto licenza GPL v2 o superiore.
Vedi LICENSE file per dettagli completi.

---

**Versione Documentazione:** 2.3.0
**Data Aggiornamento:** 2026-03-13
**Autore:** Mamba Coding
