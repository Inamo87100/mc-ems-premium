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
  // SESSIONI ACCESSIBILITÀ
  // ========================================

  const MCEMSSpecialSessions = {
    init: function() {
      this.cacheDom();
      this.bindEvents();
    },

    cacheDom: function() {
      this.$container = $('.mcems-special-sessions');
      this.$form = this.$container.find('.special-session-form');
      this.$btnAdd = this.$container.find('.btn-add-special-session');
      this.$candidateSearch = this.$form.find('input[name="candidate_search"]');
      this.$candidateResults = this.$container.find('.candidate-results');
    },

    bindEvents: function() {
      const self = this;

      // Candidate search
      this.$candidateSearch.on('keyup', function() {
        clearTimeout(self.searchTimeout);
        self.searchTimeout = setTimeout(function() {
          self.searchCandidates($(this).val());
        }, 300);
      });

      // Add button
      this.$btnAdd.on('click', function(e) {
        e.preventDefault();
        self.addSpecialSession();
      });

      // Select candidate from search results
      this.$container.on('click', '.candidate-result-item', function() {
        const candidateId = $(this).data('candidate-id');
        const candidateName = $(this).text();
        self.selectCandidate(candidateId, candidateName);
      });
    },

    searchCandidates: function(query) {
      if (query.length < 2) {
        this.$candidateResults.empty();
        return;
      }

      const self = this;

      $.ajax({
        url: mcems_premium.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'mcems_premium_search_candidates',
          nonce: mcems_premium.nonce,
          query: query
        },
        success: function(response) {
          if (response.success) {
            self.renderCandidateResults(response.data);
          }
        }
      });
    },

    renderCandidateResults: function(candidates) {
      if (candidates.length === 0) {
        this.$candidateResults.html('<div class="text-muted">Nessun candidato trovato</div>');
        return;
      }

      let html = '';
      candidates.forEach(function(candidate) {
        html += `<div class="candidate-result-item" data-candidate-id="${candidate.id}">${candidate.name} (${candidate.email})</div>`;
      });

      this.$candidateResults.html(html);
    },

    selectCandidate: function(candidateId, candidateName) {
      this.$form.find('input[name="candidate_id"]').val(candidateId);
      this.$candidateSearch.val(candidateName);
      this.$candidateResults.empty();
    },

    addSpecialSession: function() {
      const candidateId = this.$form.find('input[name="candidate_id"]').val();
      const sessionId = this.$form.find('select[name="session_id"]').val();
      const notes = this.$form.find('textarea[name="notes"]').val();

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
            self.$candidateResults.empty();
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

    MCEMSModal.init();
  });

  // Expose to global scope
  window.MCEMSPremium = {
    BookingsList: MCEMSPremiumBookingsList,
    SpecialSessions: MCEMSSpecialSessions,
    Modal: MCEMSModal
  };

})(jQuery);
