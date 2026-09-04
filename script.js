// Revenue Bar Chart
const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
new Chart(ctxRevenue, {
  type: 'bar',
  data: {
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets: [{
      label: 'Revenue',
      data: [12000, 15000, 10000, 18000, 20000, 22000, 25000, 21000, 23000, 24000, 26000, 28000],
      backgroundColor: '#0d6efd'
    },{
      label: 'Expenses',
      data: [8000, 9000, 7500, 12000, 14000, 15000, 16000, 15500, 17000, 18000, 19000, 20000],
      backgroundColor: '#ffc107'
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Sales by Category Pie Chart
const ctxCategory = document.getElementById('categoryChart').getContext('2d');
new Chart(ctxCategory, {
  type: 'doughnut',
  data: {
    labels: ['Washing','Dry Cleaning','Delivery','Other'],
    datasets: [{
      data: [45, 25, 20, 10],
      backgroundColor: ['#0d6efd','#ffc107','#20c997','#dc3545']
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
