const H=s=>{var d;return((d=document.querySelector(`meta[name="${s}"]`))==null?void 0:d.content)??""},P=()=>H("absensi-api"),F=()=>H("csrf-token");function i(s){return String(s??"").replace(/[&<>"']/g,d=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"})[d])}function O(s,d={}){const c=new URL(P(),window.location.origin);return c.searchParams.set("action",s),Object.entries(d).forEach(([l,h])=>{h!=null&&h!==""&&c.searchParams.set(l,h)}),c.toString()}async function L(s,d={}){const c=await fetch(O(s,d),{headers:{Accept:"application/json"}}),l=await c.json();if(!c.ok)throw new Error((l==null?void 0:l.message)||"Gagal memuat data.");return l}async function I(s,d={}){const c=await fetch(O(s),{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":F()},body:JSON.stringify(d)}),l=await c.json();if(!c.ok||(l==null?void 0:l.success)===!1)throw new Error((l==null?void 0:l.message)||"Gagal menyimpan data.");return l}function E(s,d,c=!0){s&&(s.textContent=d,s.className=c?"mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800":"mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800")}function B(s){s&&(s.className="hidden",s.textContent="")}function S(s,d=1){return Number(s??0).toFixed(d)}const C='<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8M7 3v5h8M5.25 3h11.5A2.25 2.25 0 0 1 19 5.25v13.5A2.25 2.25 0 0 1 16.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" /></svg>',R='<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487Z" /></svg>',M='<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487Z" /></svg>',q='<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12.003.397c.343-.059.686-.115 1.03-.166m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>',D=s=>s==="Aktif"?"bg-green-100 text-green-700":s==="Lulus"?"bg-blue-100 text-blue-700":"bg-yellow-100 text-yellow-700";function K(){const s=document.getElementById("formAnak"),d=document.getElementById("tbodyAnak"),c=document.getElementById("mobileCards"),l=document.getElementById("btnSubmit"),h=document.getElementById("btnBatal"),k=document.getElementById("btnTambah"),v=document.getElementById("anakId"),a=document.getElementById("idAnak"),o=document.getElementById("modalAnak"),e=document.getElementById("modalAnakTitle");if(!s||!d||!c||!o)return;const t={nama:document.getElementById("nama"),jenisKelamin:document.getElementById("jenisKelamin"),asalDaerah:document.getElementById("asalDaerah"),agama:document.getElementById("agama"),umur:document.getElementById("umur"),angkatan:document.getElementById("angkatan"),status:document.getElementById("status")},m=()=>{o.classList.remove("hidden"),document.body.classList.add("modal-open"),t.nama.focus()},g=()=>{o.classList.add("hidden"),document.body.classList.remove("modal-open"),k.focus()},n=()=>{s.reset(),v.value="",a.value="",l.innerHTML=`${C} Simpan`,e.textContent="Input Anak",g()},b=u=>{if(!Array.isArray(u)||u.length===0){d.innerHTML=`
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-slate-500">
                        Belum ada data anak.
                    </td>
                </tr>
            `,c.innerHTML='<p class="px-4 py-8 text-center text-slate-500">Belum ada data anak.</p>';return}d.innerHTML=u.map(r=>`
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">${i(r.id||"-")}</td>
                <td class="px-4 py-3">${i(r.nama||"-")}</td>
                <td class="px-4 py-3">${i(r.jenis_kelamin||"-")}</td>
                <td class="px-4 py-3">${i(r.asal_daerah||"-")}</td>
                <td class="px-4 py-3">${i(r.agama||"-")}</td>
                <td class="px-4 py-3">${i(r.umur||"-")}</td>
                <td class="px-4 py-3">${i(r.angkatan||"-")}</td>
                <td class="px-4 py-3">
                    <span class="rounded px-2 py-1 text-xs font-medium ${D(r.status)}">
                        ${i(r.status||"Aktif")}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex justify-center gap-2">
                        <button type="button" data-action="edit" data-id="${i(r.id)}"
                            aria-label="Edit ${i(r.nama)}"
                            class="text-xs font-medium text-blue-600 hover:text-blue-800">${M}</button>
                        <button type="button" data-action="delete" data-id="${i(r.id)}"
                            aria-label="Hapus ${i(r.nama)}"
                            class="text-xs font-medium text-red-600 hover:text-red-800">${q}</button>
                    </div>
                </td>
            </tr>
        `).join(""),c.innerHTML=u.map(r=>`
            <article class="child-mobile-card">
                <div class="child-mobile-card-heading">
                    <div class="min-w-0">
                        <h4 class="child-mobile-name">${i(r.nama||"-")}</h4>
                        <p class="child-mobile-id">${i(r.id||"-")}</p>
                    </div>
                    <div class="child-mobile-heading-actions">
                        <span class="rounded px-2 py-1 text-xs font-medium ${D(r.status)}">${i(r.status||"Aktif")}</span>
                        <button type="button" class="child-mobile-toggle" aria-expanded="false"
                            aria-label="Tampilkan detail ${i(r.nama||"")}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </div>
                </div>
                <dl class="child-mobile-details">
                    <div><dt>Jenis kelamin</dt><dd>${i(r.jenis_kelamin||"-")}</dd></div>
                    <div><dt>Asal daerah</dt><dd>${i(r.asal_daerah||"-")}</dd></div>
                    <div><dt>Agama</dt><dd>${i(r.agama||"-")}</dd></div>
                    <div><dt>Umur</dt><dd>${i(r.umur||"-")} tahun</dd></div>
                    <div><dt>Angkatan</dt><dd>${i(r.angkatan||"-")}</dd></div>
                </dl>
                <div class="child-mobile-actions">
                    <button type="button" data-action="edit" data-id="${i(r.id)}" class="child-mobile-edit">
                        ${M} Edit
                    </button>
                    <button type="button" data-action="delete" data-id="${i(r.id)}"
                        class="child-mobile-delete">
                        ${q} Hapus
                    </button>
                </div>
            </article>
        `).join("")},x=async()=>{try{b(await L("anak"))}catch(u){d.innerHTML=`<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">${i(u.message)}</td></tr>`,c.textContent=u.message}};s.addEventListener("submit",async u=>{u.preventDefault();const r={id:v.value||a.value||"",nama:t.nama.value,jenis_kelamin:t.jenisKelamin.value,asal_daerah:t.asalDaerah.value,agama:t.agama.value,umur:Number(t.umur.value),angkatan:t.angkatan.value,status:t.status.value},y=v.value?"anak_update":"anak_store";try{const $=await I(y,r);alert($.message||"Berhasil"),n(),await x()}catch($){alert($.message||"Gagal menyimpan data anak")}});const p=async u=>{const r=u.target.closest("button");if(!r)return;const{action:y,id:$}=r.dataset;if($){if(y==="edit"){try{const f=await L("anak_detail",{id:$});if(!f||f.success===!1)throw new Error(f.message||"Data tidak ditemukan");v.value=f.id,a.value=f.id,t.nama.value=f.nama||"",t.jenisKelamin.value=f.jenis_kelamin||"",t.asalDaerah.value=f.asal_daerah||"",t.agama.value=f.agama||"",t.umur.value=f.umur||0,t.angkatan.value=f.angkatan||"",t.status.value=f.status||"Aktif",l.innerHTML=`${R} Update`,e.textContent="Edit Data Anak",m()}catch(f){alert(f.message||"Data tidak dapat dibuka")}return}if(y==="delete"){if(!confirm(`Apakah Anda yakin ingin menghapus data anak ${$}?`))return;try{const A=await I("anak_delete",{id:$});alert(A.message||"Data berhasil dihapus"),await x()}catch(A){alert(A.message||"Gagal menghapus data")}}}};h.addEventListener("click",n),k.addEventListener("click",()=>{s.reset(),v.value="",a.value="",l.innerHTML=`${C} Simpan`,e.textContent="Input Anak",m()}),o.addEventListener("click",u=>{u.target.closest("[data-close-modal]")&&n()}),document.addEventListener("keydown",u=>{u.key==="Escape"&&!o.classList.contains("hidden")&&n()}),d.addEventListener("click",p),c.addEventListener("click",p),c.addEventListener("click",u=>{var f;const r=u.target.closest(".child-mobile-toggle");if(!r)return;const y=r.closest(".child-mobile-card"),$=y.classList.toggle("child-mobile-expanded");r.setAttribute("aria-expanded",String($)),r.setAttribute("aria-label",`${$?"Sembunyikan":"Tampilkan"} detail ${y.querySelector(".child-mobile-name").textContent}`),(f=r.querySelector("svg"))==null||f.classList.toggle("rotate-180",$)}),x()}function U(){const s=document.getElementById("trendChart");if(!s)return;const d=document.getElementById("dashboardMessage"),c=document.getElementById("chartEmpty"),l=document.getElementById("dashboardFilterToggle"),h=document.getElementById("dashboardFilterPanel"),k=document.getElementById("filterAngkatan"),v=document.getElementById("filterStatus"),a=document.getElementById("filterMulai"),o=document.getElementById("filterSampai"),e=document.getElementById("resetDashboardFilters"),t=()=>{const n=document.getElementById("currentDate"),b=document.getElementById("currentTime");if(!n||!b)return;const x="Asia/Jakarta",p=new Intl.DateTimeFormat("id-ID",{day:"2-digit",month:"long",year:"numeric",weekday:"long",timeZone:x}),u=new Intl.DateTimeFormat("id-ID",{hour:"2-digit",minute:"2-digit",second:"2-digit",hourCycle:"h23",timeZone:x}),r=()=>{const y=new Date;n.textContent=p.format(y),b.textContent=u.format(y)};r(),window.setInterval(r,1e3)},m=async()=>{B(d);try{const n=await L("dashboard",{angkatan:(k==null?void 0:k.value)??"",status_anak:(v==null?void 0:v.value)??"",mulai:(a==null?void 0:a.value)??"",sampai:(o==null?void 0:o.value)??""});document.getElementById("statSesuai").textContent=`${S(n.presentase.sesuai)}%`,document.getElementById("statArahan").textContent=`${S(n.presentase.arahan)}%`,document.getElementById("statTelat").textContent=`${S(n.presentase.telat)}%`,document.getElementById("statTidak").textContent=`${S(n.presentase.tidak)}%`;const b=document.getElementById("statSkor");b&&(b.textContent=`${S(n.presentase.sesuai)}%`),document.getElementById("totalAnak").textContent=n.total_anak,document.getElementById("totalKegiatan").textContent=n.total_kegiatan,document.getElementById("totalPenilaian").textContent=n.total_penilaian;const x=n.tren??[],p=Math.max(1,...x.map(u=>Number(u.total)));c.classList.toggle("hidden",x.length>0),s.innerHTML=x.map(u=>{const r=Number(u.total),y=r?Number(u.sesuai)/r*100:0;return`
                    <div class="grid grid-cols-[90px_1fr_110px] items-center gap-3 text-xs">
                        <span class="text-slate-500">${i(u.tanggal)}</span>
                        <div class="h-5 overflow-hidden rounded bg-slate-100">
                            <div class="h-full bg-green-500" style="width:${Math.max(3,r/p*100)}%"
                                title="${r} penilaian; ${y.toFixed(1)}% sesuai"></div>
                        </div>
                        <span class="text-right text-slate-600">${r} nilai · ${y.toFixed(0)}% sesuai</span>
                    </div>
                `}).join("")}catch(n){E(d,n.message,!1)}};[k,v,a,o].forEach(n=>n==null?void 0:n.addEventListener("change",m));const g=()=>{h==null||h.classList.add("hidden"),l==null||l.setAttribute("aria-expanded","false")};l==null||l.addEventListener("click",()=>{const n=l.getAttribute("aria-expanded")==="true";l.setAttribute("aria-expanded",String(!n)),h==null||h.classList.toggle("hidden",n)}),document.addEventListener("click",n=>{!(h!=null&&h.contains(n.target))&&!(l!=null&&l.contains(n.target))&&g()}),document.addEventListener("keydown",n=>{n.key==="Escape"&&(g(),l==null||l.focus())}),e==null||e.addEventListener("click",()=>{k&&(k.value=""),v&&(v.value="Aktif"),a&&(a.value=""),o&&(o.value=""),m()}),t(),m()}const _={sesuai:["bg-green-600","text-white","border-green-600"],arahan:["bg-yellow-500","text-white","border-yellow-500"],telat:["bg-orange-500","text-white","border-orange-500"],tidak:["bg-red-600","text-white","border-red-600"],libur:["bg-slate-500","text-white","border-slate-500"]},z=Object.values(_).flat();function G(){var v;const s=document.getElementById("tanggalEvaluasi"),d=document.getElementById("pesanEvaluasi");if(!s||!d)return;const c=[...document.querySelectorAll("[data-activity]")],l=(a,o)=>{var m;const e=o==="libur";a.dataset.value=o??"";const t=a.querySelector("[data-value-label]");t&&(t.textContent=e?"Sakit / Izin":"",t.classList.toggle("hidden",!e)),(m=a.querySelector("[data-eval-options]"))==null||m.classList.toggle("hidden",e),a.querySelectorAll(".eval-button").forEach(g=>{var n;g.classList.remove(...z),g.dataset.value===o&&_[o]&&g.classList.add(..._[o]),g.disabled=((n=a.closest("[data-activity]"))==null?void 0:n.dataset.scheduled)==="0"}),a.classList.toggle("bg-slate-100",e)},h=(a,o,e)=>{const t=a[o]||{aturan:"setiap_hari",hari:[],rentang:[]},m=String(new Date(`${e}T12:00:00`).getDay()||7);return t.aturan==="hari_kerja"?["1","2","3","4","5"].includes(m):t.aturan==="hari_tertentu"?(t.hari||[]).map(String).includes(m):t.aturan==="multi_rentang"?(t.rentang||[]).some(g=>g.mulai&&g.selesai&&e>=g.mulai&&e<=g.selesai):!0},k=async()=>{B(d);try{const[a,o]=await Promise.all([L("evaluasi",{tanggal:s.value}),L("settings")]);c.forEach(e=>{e.querySelectorAll("[data-child]").forEach(g=>l(g,""));const t=h(o.jadwal||{},e.dataset.activity,s.value);e.dataset.scheduled=t?"1":"0",e.querySelectorAll(".eval-button, [data-bulk], [data-save]").forEach(g=>{g.disabled=!t});const m=document.getElementById(`status-${e.dataset.activity}`);m.textContent=t?"Belum disimpan":"Tidak dijadwalkan"}),a.forEach(e=>{const t=document.querySelector(`[data-activity="${e.kegiatan_id}"]`),m=t==null?void 0:t.querySelector(`[data-child="${CSS.escape(e.anak_id)}"]`);m&&l(m,e.nilai)}),c.forEach(e=>{const t=[...e.querySelectorAll("[data-child]")],m=t.length>0&&t.every(n=>n.dataset.value),g=document.getElementById(`status-${e.dataset.activity}`);g.textContent=m?"Tersimpan":e.dataset.scheduled==="1"?"Belum disimpan":"Tidak dijadwalkan"})}catch(a){E(d,a.message,!1)}};c.forEach(a=>{var o;(o=a.querySelector("[data-accordion]"))==null||o.addEventListener("click",()=>{var e,t;(e=a.querySelector("[data-accordion-content]"))==null||e.classList.toggle("hidden"),(t=a.querySelector("[data-accordion-icon]"))==null||t.classList.toggle("rotate-180")}),a.addEventListener("click",async e=>{const t=e.target.closest("[data-bulk]");if(t){a.querySelectorAll("[data-child]").forEach(b=>l(b,t.dataset.bulk)),document.getElementById(`status-${a.dataset.activity}`).textContent="Belum disimpan";return}const m=e.target.closest(".eval-button");if(m){l(m.closest("[data-child]"),m.dataset.value),document.getElementById(`status-${a.dataset.activity}`).textContent="Belum disimpan";return}const g=e.target.closest("[data-save]");if(!g)return;const n=[...a.querySelectorAll("[data-child]")].filter(b=>b.dataset.value).map(b=>({anak_id:b.dataset.child,kegiatan_id:Number(a.dataset.activity),tanggal:s.value,nilai:b.dataset.value}));if(!n.length){E(d,"Pilih nilai untuk setidaknya satu anak sebelum menyimpan.",!1);return}g.disabled=!0;try{const b=await I("save_evaluasi_batch",{items:n});document.getElementById(`status-${a.dataset.activity}`).textContent="Tersimpan",E(d,b.message,!0)}catch(b){E(d,b.message,!1)}finally{g.disabled=!1}})}),(v=document.getElementById("simpanLibur"))==null||v.addEventListener("click",async a=>{const o=[...document.querySelectorAll("[data-leave-child]:checked")];if(!o.length){E(d,"Pilih anak yang sakit atau izin terlebih dahulu.",!1);return}const e=[];o.forEach(t=>{c.forEach(m=>{e.push({anak_id:t.dataset.leaveChild,kegiatan_id:Number(m.dataset.activity),tanggal:s.value,nilai:"libur"})})}),a.currentTarget.disabled=!0;try{await I("save_evaluasi_batch",{items:e}),await k(),E(d,`Sakit/izin disimpan untuk ${o.length} anak pada seluruh kegiatan tanggal ini.`,!0)}catch(t){E(d,t.message,!1)}finally{a.currentTarget.disabled=!1}}),s.addEventListener("change",()=>{document.querySelectorAll("[data-leave-child]").forEach(a=>{a.checked=!1}),k()}),k()}const Z=["1","2","3","4","5"];function J(){var k,v;const s=document.getElementById("pengaturanMessage"),d=[...document.querySelectorAll("[data-schedule]")];if(!s)return;const c=a=>{const o=a.querySelector("[data-rule]").value;a.querySelector("[data-days]").classList.toggle("hidden",o!=="hari_tertentu"),a.querySelector("[data-ranges]").classList.toggle("hidden",o!=="multi_rentang")},l=(a,o={mulai:"",selesai:""})=>{const e=document.createElement("div");e.className="schedule-range-row flex items-center gap-2 text-xs",e.innerHTML=`
            <input type="date" data-from aria-label="Tanggal mulai rentang aktif" value="${o.mulai??""}"
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-slate-600">
            <span class="text-slate-400">s.d.</span>
            <input type="date" data-to aria-label="Tanggal selesai rentang aktif" value="${o.selesai??""}"
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-slate-600">
            <button type="button" data-remove-range
                class="px-2 text-xs text-red-600 hover:text-red-800" title="Hapus rentang">Hapus</button>
        `,e.querySelector("[data-remove-range]").addEventListener("click",()=>e.remove()),a.appendChild(e)},h=async()=>{B(s);try{const a=await L("settings"),o=Z;d.forEach(e=>{var n;const t=e.dataset.schedule,m=((n=a.jadwal)==null?void 0:n[t])||{aturan:"setiap_hari",hari:o,rentang:[]};e.querySelector("[data-rule]").value=m.aturan,e.querySelectorAll("[data-day]").forEach(b=>{b.checked=(m.hari||[]).map(String).includes(b.dataset.day)});const g=e.querySelector("[data-range-list]");g.innerHTML="",(m.rentang||[]).forEach(b=>l(g,b)),g.children.length||l(g),c(e)}),document.querySelectorAll("[data-cohort]").forEach(e=>{var m;const t=(m=a.angkatan)==null?void 0:m.find(g=>g.angkatan===e.dataset.cohort);e.querySelector("[data-end-date]").value=(t==null?void 0:t.tanggal_selesai)||""})}catch(a){E(s,a.message,!1)}};d.forEach(a=>{a.querySelector("[data-rule]").addEventListener("change",()=>c(a)),a.querySelector("[data-add-range]").addEventListener("click",()=>{l(a.querySelector("[data-range-list]"))})}),(k=document.getElementById("simpanJadwal"))==null||k.addEventListener("click",async a=>{const o={};d.forEach(e=>{o[e.dataset.schedule]={aturan:e.querySelector("[data-rule]").value,hari:[...e.querySelectorAll("[data-day]:checked")].map(t=>t.dataset.day),rentang:[...e.querySelector("[data-range-list]").children].map(t=>({mulai:t.querySelector("[data-from]").value,selesai:t.querySelector("[data-to]").value})).filter(t=>t.mulai&&t.selesai)}}),a.currentTarget.disabled=!0;try{const e=await I("settings_save",{jadwal:o});E(s,e.message,!0)}catch(e){E(s,e.message,!1)}finally{a.currentTarget.disabled=!1}}),(v=document.getElementById("simpanPembinaan"))==null||v.addEventListener("click",async a=>{const o={};document.querySelectorAll("[data-cohort]").forEach(e=>{o[e.dataset.cohort]=e.querySelector("[data-end-date]").value}),a.currentTarget.disabled=!0;try{const e=await I("settings_save",{angkatan:o});E(s,e.message,!0)}catch(e){E(s,e.message,!1)}finally{a.currentTarget.disabled=!1}}),h()}const j=[["sesuai","Sesuai","green"],["arahan","Arahan","yellow"],["telat","Telat","orange"],["tidak","Tidak","red"]],Y={green:"border-green-200 bg-green-50 text-green-700",yellow:"border-yellow-200 bg-yellow-50 text-yellow-700",orange:"border-orange-200 bg-orange-50 text-orange-700",red:"border-red-200 bg-red-50 text-red-700"},T=s=>{const d=s.getFullYear(),c=String(s.getMonth()+1).padStart(2,"0"),l=String(s.getDate()).padStart(2,"0");return`${d}-${c}-${l}`};function V(){var b,x;const s=document.getElementById("reportAnak");if(!s)return;const d=document.getElementById("reportPeriode"),c=document.getElementById("reportMulai"),l=document.getElementById("reportSampai"),h=document.getElementById("reportMessage"),k=document.getElementById("reportIdentity"),v=document.getElementById("reportPeriod"),a=document.getElementById("reportScores"),o=document.getElementById("reportRows"),e=document.getElementById("catatanPerilaku"),t=document.getElementById("rekomendasi");let m=null;const g=()=>{const p=new Date,u=T(p);if(l.value=u,d.value==="mingguan"){const y=new Date(p);y.setDate(p.getDate()-6),c.value=T(y)}else d.value==="bulanan"?c.value=T(new Date(p.getFullYear(),p.getMonth(),1)):d.value==="harian"&&(c.value=u);const r=d.value==="custom";c.classList.toggle("hidden",!r),l.classList.toggle("hidden",!r)},n=async()=>{if(s.value){if(B(h),d.value!=="custom"&&g(),!c.value||!l.value||c.value>l.value){E(h,"Rentang tanggal rapor tidak valid.",!1);return}try{const p=await L("report",{id:s.value,mulai:c.value,sampai:l.value});if(!p.anak)throw new Error(p.message||"Rapor tidak dapat dimuat.");m=p;const u=p.anak;k.innerHTML=`
                <p class="mb-1"><span class="inline-block w-40 font-medium">ID &amp; Nama Anak:</span> ${i(u.id)} · ${i(u.nama)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Umur &amp; Agama:</span> ${i(u.umur)} tahun · ${i(u.agama)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Asal Daerah:</span> ${i(u.asal_daerah)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Angkatan:</span> ${i(u.angkatan)}</p>
            `,v.innerHTML=`
                <p class="mb-1"><span class="inline-block w-32 font-medium">Tanggal Catat:</span> ${i(p.tanggal_catat)}</p>
                <p class="mb-1"><span class="inline-block w-32 font-medium">Status:</span> ${i(u.status)}</p>
                <p class="mb-1"><span class="inline-block w-32 font-medium">Periode:</span> ${i(p.periode_mulai)} s.d. ${i(p.periode_selesai)}</p>
                <p class="mb-1"><span class="inline-block w-32 font-medium">Standar:</span> ${(p.standar_evaluasi||[]).map(i).join(", ")}</p>
            `;const r=j.reduce((y,[$])=>y+Number(p.ringkasan.status[$]||0),0);a.innerHTML=j.map(([y,$,f])=>`
                <div class="rounded-lg border py-3 ${Y[f]}">
                    <p class="text-xs font-bold">${$}</p>
                    <p class="text-xl font-bold">${Number(p.ringkasan.status[y]||0)}</p>
                    <p class="text-xs">${r?(Number(p.ringkasan.status[y]||0)/r*100).toFixed(1):"0.0"}%</p>
                </div>
            `).join("")+`
                <div class="rounded-lg border border-blue-200 bg-blue-50 py-3 text-blue-700">
                    <p class="text-xs font-bold">SKOR</p>
                    <p class="text-xl font-bold">${S(p.ringkasan.skor_total)}%</p>
                    <p class="text-xs">Total</p>
                </div>
            `,o.innerHTML=p.ringkasan.detail.map(y=>`
                <tr class="border-b border-slate-100">
                    <td data-label="No" class="px-3 py-2 text-center">${Number(y.no)}</td>
                    <td data-label="Kegiatan" class="px-3 py-2">${i(y.kegiatan)}</td>
                    <td data-label="Sesuai" class="px-3 py-2 text-center">${Number(y.sesuai)}</td>
                    <td data-label="Arahan" class="px-3 py-2 text-center">${Number(y.arahan)}</td>
                    <td data-label="Telat" class="px-3 py-2 text-center">${Number(y.telat)}</td>
                    <td data-label="Tidak" class="px-3 py-2 text-center">${Number(y.tidak)}</td>
                </tr>
            `).join(""),e.value=p.catatan||"",t.value=p.rekomendasi||""}catch(p){E(h,p.message,!1)}}};d.addEventListener("change",n),s.addEventListener("change",n),c.addEventListener("change",()=>d.value==="custom"&&n()),l.addEventListener("change",()=>d.value==="custom"&&n()),(b=document.getElementById("btnCetak"))==null||b.addEventListener("click",()=>window.print()),(x=document.getElementById("btnSimpanCatatan"))==null||x.addEventListener("click",async p=>{if(m!=null&&m.anak){p.currentTarget.disabled=!0;try{const u=await I("report_notes_save",{anak_id:s.value,periode_mulai:c.value,periode_selesai:l.value,catatan:e.value,rekomendasi:t.value});E(h,u.message,!0)}catch(u){E(h,u.message,!1)}finally{p.currentTarget.disabled=!1}}}),n()}const N=[["sesuai","SESUAI","green"],["arahan","ARAHAN","yellow"],["telat","TERLAMBAT","orange"],["tidak","TIDAK","red"]],w={green:{box:"border-green-200 bg-green-50",title:"text-green-700",value:"text-green-600"},yellow:{box:"border-yellow-200 bg-yellow-50",title:"text-yellow-700",value:"text-yellow-600"},orange:{box:"border-orange-200 bg-orange-50",title:"text-orange-700",value:"text-orange-600"},red:{box:"border-red-200 bg-red-50",title:"text-red-700",value:"text-red-600"}};function W(){var o;const s=document.getElementById("pilihAnak");if(!s)return;const d=document.getElementById("mulai"),c=document.getElementById("sampai"),l=document.getElementById("anakProfile"),h=document.getElementById("summaryCards"),k=document.getElementById("detailRows"),v=document.getElementById("summaryMessage"),a=async()=>{if(B(v),!s.value){l.innerHTML='<p class="text-slate-500">Belum ada data anak untuk ditampilkan.</p>',h.innerHTML="",k.innerHTML="";return}try{const e=await L("summary",{id:s.value,mulai:d.value,sampai:c.value});if(!e.anak)throw new Error("Data anak tidak ditemukan.");const t=e.anak,m=String(t.nama||"").split(/\s+/).slice(0,2).map(n=>n.charAt(0)).join("").toUpperCase();l.innerHTML=`
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-blue-600 text-2xl font-bold text-white">
                    ${i(m)}
                </div>
                <div>
                    <h3 class="text-lg font-bold">${i(t.nama)}</h3>
                    <p class="text-sm text-slate-500">${i(t.jenis_kelamin)} · ${i(t.status)}</p>
                </div>
                <div class="text-sm md:ml-auto md:text-right">
                    <p class="text-slate-500">Asal Daerah &amp; Agama: ${i(t.asal_daerah)} · ${i(t.agama)}</p>
                    <p class="text-slate-500">ID, Umur &amp; Angkatan: ${i(t.id)} · ${i(t.umur)} tahun · ${i(t.angkatan)}</p>
                </div>
            `;const g=N.reduce((n,[b])=>n+Number(e.status[b]||0),0);h.innerHTML=N.map(([n,b,x])=>`
                <div class="rounded-lg border py-4 ${w[x].box}">
                    <p class="mb-1 text-xs font-bold ${w[x].title}">${b}</p>
                    <p class="text-2xl font-bold ${w[x].value}">${Number(e.status[n]||0)}</p>
                    <p class="text-xs ${w[x].value}">${g?(Number(e.status[n]||0)/g*100).toFixed(1):"0.0"}%</p>
                </div>
            `).join("")+`
                <div class="rounded-lg border border-blue-200 bg-blue-50 py-4">
                    <p class="mb-1 text-xs font-bold text-blue-700">SKOR TOTAL</p>
                    <p class="text-2xl font-bold text-blue-600">${S(e.skor_total)}%</p>
                </div>
            `,k.innerHTML=e.detail.map(n=>`
                <tr class="border-b border-slate-100">
                    <td data-label="No" class="px-4 py-3">${Number(n.no)}</td>
                    <td data-label="Kegiatan" class="px-4 py-3">${i(n.kegiatan)}</td>
                    <td data-label="Sesuai" class="px-4 py-3 text-center">${Number(n.sesuai)}</td>
                    <td data-label="Arahan" class="px-4 py-3 text-center">${Number(n.arahan)}</td>
                    <td data-label="Telat" class="px-4 py-3 text-center">${Number(n.telat)}</td>
                    <td data-label="Tidak" class="px-4 py-3 text-center">${Number(n.tidak)}</td>
                    <td data-label="Libur" class="px-4 py-3 text-center">${Number(n.libur)}</td>
                    <td data-label="Skor" class="px-4 py-3 text-center font-semibold">${S(n.skor)}%</td>
                </tr>
            `).join("")}catch(e){E(v,e.message,!1)}};s.addEventListener("change",a),d.addEventListener("change",a),c.addEventListener("change",a),(o=document.getElementById("refreshSummary"))==null||o.addEventListener("click",a),a()}const X={dashboard:U,input:G,anak:K,summary:W,rapor:V,pengaturan:J};document.addEventListener("DOMContentLoaded",()=>{const s=document.body.dataset.absensiPage,d=X[s];typeof d=="function"&&d()});
