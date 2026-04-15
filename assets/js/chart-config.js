/**
 * MC-EMS Premium - Chart.js Configuration
 * Configurazione grafici per dashboard reports
 */

(function() {
  'use strict';

  const MCEMSCharts = {
    defaultOptions: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: {
            font: {
              size: 13,
              family: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif"
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            font: {
              size: 12
            }
          }
        },
        x: {
          ticks: {
            font: {
              size: 12
            }
          }
        }
      }
    },

    colors: {
      primary: '#0073aa',
      success: '#28a745',
      warning: '#ffc107',
      danger: '#dc3545',
      info: '#17a2b8',
      gray: '#6c757d'
    },

    // Trend prenotazioni nel tempo (Line chart)
    trendChart: function(canvasId, data) {
      const ctx = document.getElementById(canvasId);
      if (!ctx) return;

      const chartData = {
        labels: data.labels,
        datasets: [{
          label: 'Prenotazioni',
          data: data.values,
          borderColor: this.colors.primary,
          backgroundColor: 'rgba(0, 115, 170, 0.1)',
          borderWidth: 2,
          tension: 0.4,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: this.colors.primary,
          pointHoverRadius: 6
        }]
      };

      const options = Object.assign({}, this.defaultOptions, {
        plugins: Object.assign({}, this.defaultOptions.plugins, {
          title: {
            display: true,
            text: 'Trend Prenotazioni'
          }
        })
      });

      return new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: options
      });
    },

    // Prenotazioni per sessione (Bar chart)
    sessionChart: function(canvasId, data) {
      const ctx = document.getElementById(canvasId);
      if (!ctx) return;

      const chartData = {
        labels: data.labels,
        datasets: [{
          label: 'Prenotazioni per Sessione',
          data: data.values,
          backgroundColor: [
            this.colors.primary,
            this.colors.success,
            this.colors.info,
            this.colors.warning,
            this.colors.danger
          ],
          borderColor: '#fff',
          borderWidth: 1
        }]
      };

      const options = Object.assign({}, this.defaultOptions, {
        indexAxis: 'y',
        plugins: Object.assign({}, this.defaultOptions.plugins, {
          title: {
            display: true,
            text: 'Prenotazioni per Sessione'
          }
        })
      });

      return new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: options
      });
    },

    // Distribuzione corsi (Doughnut chart)
    courseDistributionChart: function(canvasId, data) {
      const ctx = document.getElementById(canvasId);
      if (!ctx) return;

      const colors = [
        this.colors.primary,
        this.colors.success,
        this.colors.warning,
        this.colors.danger,
        this.colors.info
      ];

      const chartData = {
        labels: data.labels,
        datasets: [{
          data: data.values,
          backgroundColor: colors.slice(0, data.values.length),
          borderColor: '#fff',
          borderWidth: 2
        }]
      };

      const options = Object.assign({}, this.defaultOptions, {
        plugins: Object.assign({}, this.defaultOptions.plugins, {
          legend: Object.assign({}, this.defaultOptions.plugins.legend, {
            position: 'right'
          })
        })
      });

      return new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: options
      });
    },

    // Occupazione percentuale per sessione (Radar chart)
    occupancyChart: function(canvasId, data) {
      const ctx = document.getElementById(canvasId);
      if (!ctx) return;

      const chartData = {
        labels: data.labels,
        datasets: [{
          label: 'Occupazione %',
          data: data.values,
          borderColor: this.colors.primary,
          backgroundColor: 'rgba(0, 115, 170, 0.2)',
          borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: this.colors.primary
        }]
      };

      const options = Object.assign({}, this.defaultOptions, {
        scales: Object.assign({}, this.defaultOptions.scales, {
          r: {
            beginAtZero: true,
            max: 100,
            ticks: {
              stepSize: 20
            }
          }
        })
      });

      return new Chart(ctx, {
        type: 'radar',
        data: chartData,
        options: options
      });
    },

    // Prenotazioni per corso (Horizontal bar)
    courseBookingsChart: function(canvasId, data) {
      const ctx = document.getElementById(canvasId);
      if (!ctx) return;

      const chartData = {
        labels: data.labels,
        datasets: [{
          label: 'Prenotazioni',
          data: data.values,
          backgroundColor: this.colors.primary,
          borderColor: '#fff',
          borderWidth: 1
        }]
      };

      const options = Object.assign({}, this.defaultOptions, {
        indexAxis: 'y',
        scales: Object.assign({}, this.defaultOptions.scales, {
          x: Object.assign({}, this.defaultOptions.scales.x, {
            beginAtZero: true
          })
        })
      });

      return new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: options
      });
    },

    // Status prenotazioni (Pie chart)
    statusChart: function(canvasId, data) {
      const ctx = document.getElementById(canvasId);
      if (!ctx) return;

      const chartData = {
        labels: ['Confermata', 'In Sospeso', 'Cancellata'],
        datasets: [{
          data: data.values,
          backgroundColor: [
            this.colors.success,
            this.colors.warning,
            this.colors.danger
          ],
          borderColor: '#fff',
          borderWidth: 2
        }]
      };

      const options = Object.assign({}, this.defaultOptions);

      return new Chart(ctx, {
        type: 'pie',
        data: chartData,
        options: options
      });
    },

    // Initialize all charts on page
    initializeAll: function() {
      const self = this;

      // Trend chart
      const $trendChart = jQuery('#mcems-trend-chart');
      if ($trendChart.length) {
        jQuery.ajax({
          url: mcems_premium.ajax_url,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'mcems_premium_get_trend_data',
            nonce: mcems_premium.nonce
          },
          success: function(response) {
            if (response.success) {
              self.trendChart('mcems-trend-chart', response.data);
            }
          }
        });
      }

      // Session chart
      const $sessionChart = jQuery('#mcems-session-chart');
      if ($sessionChart.length) {
        jQuery.ajax({
          url: mcems_premium.ajax_url,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'mcems_premium_get_session_data',
            nonce: mcems_premium.nonce
          },
          success: function(response) {
            if (response.success) {
              self.sessionChart('mcems-session-chart', response.data);
            }
          }
        });
      }

      // Course distribution
      const $courseChart = jQuery('#mcems-course-chart');
      if ($courseChart.length) {
        jQuery.ajax({
          url: mcems_premium.ajax_url,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'mcems_premium_get_course_data',
            nonce: mcems_premium.nonce
          },
          success: function(response) {
            if (response.success) {
              self.courseDistributionChart('mcems-course-chart', response.data);
            }
          }
        });
      }

      // Occupancy chart
      const $occupancyChart = jQuery('#mcems-occupancy-chart');
      if ($occupancyChart.length) {
        jQuery.ajax({
          url: mcems_premium.ajax_url,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'mcems_premium_get_occupancy_data',
            nonce: mcems_premium.nonce
          },
          success: function(response) {
            if (response.success) {
              self.occupancyChart('mcems-occupancy-chart', response.data);
            }
          }
        });
      }
    }
  };

  // Initialize on document ready
  jQuery(document).ready(function() {
    if (typeof jQuery !== 'undefined' && typeof Chart !== 'undefined') {
      MCEMSCharts.initializeAll();
    }
  });

  // Expose to global scope
  window.MCEMSCharts = MCEMSCharts;

})();
