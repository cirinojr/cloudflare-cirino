const addUTMandTimestamp=()=> {
  const links = document.querySelectorAll('a');

  links.forEach((link)=> {
      const originalHref = link.getAttribute('href');

      const utmAddedHref = originalHref + (originalHref.includes('?') ? '&' : '?') + 'utm=' + utmData.utmParameter;

      const timestampAddedHref = utmAddedHref + '&cfk=' + new Date().getTime();
   
      link.setAttribute('href', timestampAddedHref);
  });
}


document.addEventListener('DOMContentLoaded', ()=> {
  addUTMandTimestamp();
});