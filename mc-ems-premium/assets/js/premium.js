/**
 * MC-EMS Premium - JavaScript Features
 * Funzioni per lista avanzata, sessioni speciali, export, reports
 */

(function($) {
  'use strict';

  // ========================================
  // LISTA PRENOTAZIONI AVANZATA
  // ========================================

  const MCEMSPremiumBookingsList = {
    init: function() {
      this.cacheDom();
      this.bindEvents();
    },

    cacheDom: function() {
      this.$container = $('.mcems-bookings-list-premium');
      this.$filterForm = this.$container.find('.filters');
      this.$btnSearch = this.$filterForm.find('.btn-search');
      this.$btnReset = this.$filterForm.find('.btn-reset');
      this.$btnExport = this.$filterForm.find('.btn-export');
      this.$table = this.$container.find('table');
      this.$tableBody = this.$table.find('tbody');
      this.$paginationContainer = this.$container.find('.pagination');
    },

    bindEvents: function() {
      const self = this;

      // Search button
      this.$btnSearch.on('click', function(e) {
        e.preventDefault();
        self.searchBookings();
      });

      // Reset filters
      this.$btnReset.on('click', function(e) {
        e.preventDefault();
        self.resetFilters();
      });

      // Export CSV
      this.$btnExport.on('click', function(e) {
        e.preventDefault();
        self.exportToCSV();
      });

      // Table sorting
      this.$table.find('thead th').on('click', function() {
        const column = $(this).data('column');
        self.sortTable(column);
      });

      // Pagination
      this.$paginationContainer.on('click', 'a', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        self.loadPage(page);
      });

      // Real-time search
      this.$filterForm.find('input[name="search"]').on('keyup', function() {
        clearTimeout(self.searchTimeout);
        self.searchTimeout = setTimeout(function() {
          self.liveSearch($(this).val());
        }, 300);
      });
    },

    searchBookings: function() {
      const filters = this.getFilters();
      const self = this;

      $.ajax({
        url: mcems_premium.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'mcems_premium_search_bookings',
          nonce: mcems_premium.nonce,
          filters: filters
        },
        beforeSend: function() {
          self.$container.addClass('loading');
        },
        success: function(response) {
          if (response.success) {
            self.renderTable(response.data.bookings);
            self.renderPagination(response.data.pagination);
          } else {
            alert('Errore nella ricerca: ' + response.data);
          }
        },
        complete: function() {
          self.$container.removeClass('loading');
        }
      });
    },

    getFilters: function() {
      const filters = {};

      this.$filterForm.find('input, select').each(function() {
        const name = $(this).attr('name');
        const value = $(this).val();
        if (name && value) {
          filters[name] = value;
        }
      });

      return filters;
    },

    renderTable: function(bookings) {
      if (bookings.length === 0) {
        this.$tableBody.html('<tr><td colspan="100%" class="text-center">Nessuna prenotazione trovata</td></tr>');
        return;
      }

      let html = '';
      bookings.forEach(function(booking) {
        const statusClass = 'status-' + booking.status;
        html += `
          <tr class="${statusClass}">
            <td>${booking.id}</td>
            <td>${booking.candidate_name}</td>
            <td>${booking.course_name}</td>
            <td>${booking.session_date}</td>
            <td>${booking.status}</td>
            <td><small>${booking.created_at}</small></td>
          </tr>
        `;
      });

      this.$tableBody.html(html);
    },

    renderPagination: function(pagination) {
      let html = '';

      if (pagination.prev_page) {
        html += `<a href="#" data-page="${pagination.prev_page}">← Precedente</a>`;
      }

      for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
          html += `<span class="current">${i}</span>`;
        } else {
          html += `<a href="#" data-page="${i}">${i}</a>`;
        }
      }

      if (pagination.next_page) {
        html += `<a href="#" data-page="${pagination.next_page}">Successivo →</a>`;
      }

      this.$paginationContainer.html(html);
    },

    resetFilters: function() {
      this.$filterForm.find('input, select').val('');
      this.searchBookings();
    },

    sortTable: function(column) {
      const $th = this.$table.find(`thead th[data-column="${column}"]`);
      const order = $th.data('order') === 'asc' ? 'desc' : 'asc';

      this.$table.find('thead th').removeData('order');
      $th.data('order', order);

      this.searchBookings();
    },

    loadPage: function(page) {
      const filters = this.getFilters();
      filters.page = page;

      const self = this;

      $.ajax({
        url: mcems_premium.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'mcems_premium_search_bookings',
          nonce: mcems_premium.nonce,
          filters: filters
        },
        success: function(response) {
          if (response.success) {
            self.renderTable(response.data.bookings);
            self.renderPagination(response.data.pagination);
            $(window).scrollTop(self.$container.offset().top - 100);
          }
        }
      });
    },

    liveSearch: function(query) {
      if (query.length < 2) return;

      const self = this;
      const filters = this.getFilters();
      filters.search = query;

      $.ajax({
        url: mcems_premium.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'mcems_premium_search_bookings',
          nonce: mcems_premium.nonce,
          filters: filters
        },
        success: function(response) {
          if (response.success) {
            self.renderTable(response.data.bookings);
          }
        }
      });
    },

    exportToCSV: function() {
      const filters = this.getFilters();
      const self = this;

      $.ajax({
        url: mcems_premium.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'mcems_premium_export_bookings',
          nonce: mcems_premium.nonce,
          filters: filters
        },
        success: function(response) {
          if (response.success) {
            // Trigger download
            const csv = response.data;
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', 'prenotazioni-' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          } else {
            alert('Errore nell\'export: ' + response.data);
          }
        }
      });
    }
  };

  // ========================================
  // USER SEARCH SELECTOR (componente riutilizzabile)
  // ========================================

  const MCEMSUserSearchSelector = {
    /**
     * Crea un'istanza del selettore utente.
     * @param {Object} opts
     * @param {jQuery} opts.$search   - input di ricerca testo
     * @param {jQuery} opts.$results  - container dei risultati dropdown
     * @param {jQuery} opts.$hidden   - input hidden per l'ID selezionato
     * @param {jQuery} opts.$selected - container per il badge di selezione
     * @param {string} [opts.searchFor='candidate'] - tipo di ricerca: 'proctor' | 'candidate' | 'associated_candidate'
     */
    create: function(opts) {
      const inst = {
        $search:    opts.$search,
        $results:   opts.$results,
        $hidden:    opts.$hidden,
        $selected:  opts.$selected,
        searchFor:  opts.searchFor || 'candidate',
        _timer:     null,

        init: function() {
          const self = this;
          // 200 ms: allows a click on a result item to register before the
          // blur event fires and closes the dropdown.
          const BLUR_HIDE_DELAY = 200;

          // Use 'input' event to skip non-character keys (arrows, shift, etc.)
          this.$search.on('input', function() {
            clearTimeout(self._timer);
            const q = $(this).val();
            self._timer = setTimeout(function() { self.search(q); }, 300);
          });

          this.$search.on('blur', function() {
            setTimeout(function() { self.$results.empty().hide(); }, BLUR_HIDE_DELAY);
          });

          this.$results.on('click', '.mcems-user-result-item', function() {
            self.select(
              $(this).data('id'),
              $(this).data('firstName'),
              $(this).data('lastName'),
              $(this).data('email')
            );
          });

          this.$selected.on('click', '.mcems-user-clear', function() {
            self.clear();
          });

          return this;
        },

        search: function(q) {
          if (!q || q.length < 2) {
            this.$results.empty().hide();
            return;
          }
          const self = this;
          $.ajax({
            url: mcems_premium.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
              action:     'mcems_premium_search_users',
              nonce:      mcems_premium.nonce,
              query:      q,
              search_for: self.searchFor
            },
            success: function(resp) {
              if (resp.success) { self.renderResults(resp.data); }
            }
          });
        },

        renderResults: function(list) {
          this.$results.empty();
          if (!list || !list.length) {
            $('<div class="mcems-user-no-results">').text('Nessun utente trovato').appendTo(this.$results);
            this.$results.show();
            return;
          }
          const self = this;
          list.forEach(function(c) {
            const full = [c.first_name, c.last_name].filter(Boolean).join(' ') || c.display_name || c.name || '';
            const $item = $('<div class="mcems-user-result-item">')
              .data('id', c.id)
              .data('firstName', c.first_name || '')
              .data('lastName',  c.last_name  || '')
              .data('email',     c.email);
            $('<strong class="mcems-user-fullname">').text(full).appendTo($item);
            $('<span class="mcems-user-email">').text(c.email).appendTo($item);
            self.$results.append($item);
          });
          this.$results.show();
        },

        select: function(id, firstName, lastName, email) {
          const full = [firstName, lastName].filter(Boolean).join(' ') || email || '';
          this.$hidden.val(id);
          this.$search.val('').hide();
          this.$results.empty().hide();

          const $badge = $('<span class="mcems-user-selected-badge">');
          $('<span class="mcems-check-icon">').text('✓').appendTo($badge);
          $('<strong class="mcems-user-fullname">').text(full).appendTo($badge);
          if (email) {
            $('<span class="mcems-user-email">').text('(' + email + ')').appendTo($badge);
          }
          $('<button type="button" class="mcems-user-clear">').text('Rimuovi').appendTo($badge);
          this.$selected.empty().append($badge).show();
        },

        clear: function() {
          this.$hidden.val('');
          this.$selected.empty().hide();
          this.$search.val('').show();
        }
      };

      return inst.init();
    }
  };

  // ========================================
  // SESSIONI ACCESSIBILITÀ
  // ========================================

  const MCEMSSpecialSessions = {
    init: function() {
      this.cacheDom();
      this.bindEvents();
    },

    cacheDom: function() {
      this.$container        = $('.mcems-special-sessions');
      this.$form             = this.$container.find('.special-session-form');
      this.$btnAdd           = this.$container.find('.btn-add-special-session');
      this.$candidateSearch  = this.$form.find('input[name="candidate_search"]');
      this.$candidateResults = this.$container.find('.candidate-results');
      this.$candidateId      = this.$form.find('input[name="candidate_id"]');

      // Inject selected-display element if not already present
      if (!this.$form.find('.candidate-selected').length) {
        this.$candidateSearch.after('<div class="candidate-selected" style="display:none;"></div>');
      }
      this.$candidateSelected = this.$form.find('.candidate-selected');

      // Mark results container for shared CSS
      this.$candidateResults.addClass('mcems-user-results');
      this.$candidateResults.hide();
    },

    bindEvents: function() {
      const self = this;

      if (this.$candidateSearch.length) {
        this._selector = MCEMSUserSearchSelector.create({
          $search:   this.$candidateSearch,
          $results:  this.$candidateResults,
          $hidden:   this.$candidateId,
          $selected: this.$candidateSelected
        });
      }

      this.$btnAdd.on('click', function(e) {
        e.preventDefault();
        self.addSpecialSession();
      });
    },

    // Kept for backward compatibility
    searchCandidates: function(query) {
      if (this._selector) { this._selector.search(query); }
    },

    renderCandidateResults: function(candidates) {
      if (this._selector) { this._selector.renderResults(candidates); }
    },

    selectCandidate: function(candidateId, candidateName) {
      if (this._selector) {
        this._selector.select(candidateId, '', '', candidateName || '');
      } else {
        this.$form.find('input[name="candidate_id"]').val(candidateId);
        this.$candidateSearch.val(candidateName);
        this.$candidateResults.empty();
      }
    },

    addSpecialSession: function() {
      const candidateId = this.$candidateId.val();
      const sessionId   = this.$form.find('select[name="session_id"]').val();
      const notes       = this.$form.find('textarea[name="notes"]').val();

      if (!candidateId || !sessionId) {
        alert('Seleziona un candidato e una sessione');
        return;
      }

      const self = this;

      $.ajax({
        url: mcems_premium.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'mcems_premium_add_special_session',
          nonce: mcems_premium.nonce,
          candidate_id: candidateId,
          session_id: sessionId,
          notes: notes
        },
        beforeSend: function() {
          self.$form.addClass('loading');
        },
        success: function(response) {
          if (response.success) {
            alert('Sessione speciale aggiunta con successo!');
            self.$form[0].reset();
            self.$candidateResults.empty().hide();
            self.$candidateSelected.empty().hide();
            self.$candidateSearch.show();
          } else {
            alert('Errore: ' + response.data);
          }
        },
        complete: function() {
          self.$form.removeClass('loading');
        }
      });
    }
  };

  // ========================================
  // SESSION EDIT – Proctor & Associated Candidate
  // ========================================

  const MCEMSSessionEdit = {
    init: function() {
      this._initProctorFields();
      this._initAssocCandidateFields();
    },

    _initProctorFields: function() {
      const self = this;
      $('.mcems-proctor-field').each(function() {
        self._initField($(this), {
          searchSel:   '.mcems-proctor-search',
          resultsSel:  '.mcems-proctor-results',
          hiddenSel:   'input[name="proctor_id"]',
          selectedSel: '.mcems-proctor-selected',
          searchFor:   'proctor'
        });
      });
    },

    _initAssocCandidateFields: function() {
      const self = this;
      $('.mcems-assoc-candidate-field').each(function() {
        self._initField($(this), {
          searchSel:   '.mcems-assoc-candidate-search',
          resultsSel:  '.mcems-assoc-candidate-results',
          hiddenSel:   'input[name="assoc_candidate_id"]',
          selectedSel: '.mcems-assoc-candidate-selected',
          searchFor:   'associated_candidate'
        });
      });
    },

    _initField: function($wrap, sels) {
      const $search   = $wrap.find(sels.searchSel);
      const $results  = $wrap.find(sels.resultsSel);
      const $hidden   = $wrap.find(sels.hiddenSel);
      const $selected = $wrap.find(sels.selectedSel);

      if (!$search.length || !$hidden.length) { return; }

      // If a value is already stored, show the pre-filled badge.
      // The hidden input must have data-first-name, data-last-name, and data-email
      // attributes set server-side when the form is pre-populated.
      const existingId = $hidden.val();
      if (existingId) {
        const fn    = $hidden.data('firstName') || '';
        const ln    = $hidden.data('lastName')  || '';
        const email = $hidden.data('email')     || '';
        if (fn || ln || email) {
          const full = [fn, ln].filter(Boolean).join(' ') || email;
          const $badge = $('<span class="mcems-user-selected-badge">');
          $('<span class="mcems-check-icon">').text('✓').appendTo($badge);
          $('<strong class="mcems-user-fullname">').text(full).appendTo($badge);
          if (email) {
            $('<span class="mcems-user-email">').text('(' + email + ')').appendTo($badge);
          }
          $('<button type="button" class="mcems-user-clear">').text('Rimuovi').appendTo($badge);
          $selected.empty().append($badge).show();
          $search.hide();
        }
      }

      MCEMSUserSearchSelector.create({
        $search:   $search,
        $results:  $results,
        $hidden:   $hidden,
        $selected: $selected,
        searchFor: sels.searchFor || 'candidate'
      });
    }
  };

  // ========================================
  // MODAL DIALOGS
  // ========================================

  const MCEMSModal = {
    open: function(modalId) {
      $(`#${modalId}`).addClass('active');
      $('body').css('overflow', 'hidden');
    },

    close: function(modalId) {
      $(`#${modalId}`).removeClass('active');
      $('body').css('overflow', 'auto');
    },

    init: function() {
      const self = this;

      // Close button
      $(document).on('click', '.mcems-modal-close', function() {
        const modal = $(this).closest('.mcems-modal-overlay');
        self.close(modal.attr('id'));
      });

      // Overlay click
      $(document).on('click', '.mcems-modal-overlay.active', function(e) {
        if ($(e.target).hasClass('mcems-modal-overlay')) {
          self.close($(this).attr('id'));
        }
      });

      // Escape key
      $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
          $('.mcems-modal-overlay.active').each(function() {
            self.close($(this).attr('id'));
          });
        }
      });
    }
  };

  // ========================================
  // INITIALIZATION
  // ========================================

  $(document).ready(function() {
    // Initialize all components
    if ($('.mcems-bookings-list-premium').length) {
      MCEMSPremiumBookingsList.init();
    }

    if ($('.mcems-special-sessions').length) {
      MCEMSSpecialSessions.init();
    }

    MCEMSSessionEdit.init();

    MCEMSModal.init();
  });

  // Expose to global scope
  window.MCEMSPremium = {
    BookingsList:       MCEMSPremiumBookingsList,
    SpecialSessions:    MCEMSSpecialSessions,
    SessionEdit:        MCEMSSessionEdit,
    UserSearchSelector: MCEMSUserSearchSelector,
    Modal:              MCEMSModal
  };

})(jQuery);
