(function(){'use strict';
function initSidebar(){
 const sidebar=document.getElementById('flexihubSidebar'); if(!sidebar)return;
 const sections=Array.from(sidebar.querySelectorAll('.sidebar-menu-section'));
 sections.forEach(function(section,index){
  const heading=section.querySelector('.sidebar-menu-heading'); if(!heading)return;
  heading.setAttribute('role','button'); heading.setAttribute('tabindex','0');
  const key='flexihub.sidebar.section.'+index;
  const links=Array.from(section.querySelectorAll('.sidebar-menu-link'));
  const active=links.some(function(link){return link.classList.contains('active')});
  const saved=localStorage.getItem(key);
  const open=saved===null?active:saved==='open';
  section.classList.toggle('is-collapsed',!open); section.classList.toggle('is-active-open',active&&open);
  function toggle(){const nowOpen=section.classList.contains('is-collapsed');section.classList.toggle('is-collapsed',!nowOpen);section.classList.toggle('is-active-open',active&&nowOpen);localStorage.setItem(key,nowOpen?'open':'closed');}
  heading.addEventListener('click',toggle); heading.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();}});
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initSidebar);else initSidebar();
})();