const fileInput  = document.getElementById('heroFileInput');
const preview    = document.getElementById('hero-preview');
const hint       = document.getElementById('heroHint');
const uploadZone = document.getElementById('heroUploadZone');
const myChart = echarts.init(document.getElementById('resultadoVenda'))

if (fileInput && preview && hint && uploadZone) {
  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      preview.style.display = 'block';
      hint.style.display = 'none';
      uploadZone.style.border = 'none';
    };
    reader.readAsDataURL(file);
  });
}



let option = {
        title: {
          text: 'Resultado de Vendas'
        },
        tooltip: {},
        legend: {
          data: ['sales']
        },
        xAxis: {
          data: ['Shirts', 'Cardigans', 'Chiffons', 'Pants', 'Heels', 'Socks']
        },
        yAxis: {},
        series: [
          {
            name: 'sales',
            type: 'bar',
            data: [5, 20, 36, 10, 10, 20]
          }
        ]
      };

myChart.setOption(option);