document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.fi-ta-content table').forEach((table) => {
    // grab headers
    const headers = Array.from(table.querySelectorAll('thead th'))
      .map(th => th.textContent.trim());

    // assign to each cell
    table.querySelectorAll('tbody tr').forEach((tr) => {
      tr.querySelectorAll('td').forEach((td, i) => {
        td.setAttribute('data-label', headers[i] || '');
      });
    });
  });
});
