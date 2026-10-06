(()=>{
  const q=(s,c=document)=>c.querySelector(s);
  const qa=(s,c=document)=>[...c.querySelectorAll(s)];

  q('[data-sidebar-toggle]')?.addEventListener('click',()=>q('#sidebar')?.classList.toggle('open'));
  document.addEventListener('click',e=>{
    const sb=q('#sidebar');
    if(sb?.classList.contains('open')&&!sb.contains(e.target)&&!e.target.closest('[data-sidebar-toggle]')) sb.classList.remove('open');
  });

  qa('[data-fill-login]').forEach(btn=>btn.addEventListener('click',()=>{
    const email=q('input[name=email]');
    if(email) email.value=btn.dataset.fillLogin;
  }));

  qa('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{
    const message=form.dataset.confirm||'Confirmar esta ação?';
    if(!window.confirm(message)) e.preventDefault();
  }));

  qa('[data-filter-table]').forEach(box=>{
    const search=q('[data-filter-search]',box);
    const status=q('[data-filter-status]',box);
    const rows=qa('[data-filter-row]',box);
    const empty=q('[data-filter-empty]',box);
    const apply=()=>{
      const term=(search?.value||'').trim().toLocaleLowerCase('pt-BR');
      const st=status?.value||'';
      let visible=0;
      rows.forEach(row=>{
        const okTerm=!term||(row.dataset.search||'').includes(term);
        const okStatus=!st||row.dataset.status===st;
        row.hidden=!(okTerm&&okStatus);
        if(!row.hidden) visible++;
      });
      if(empty) empty.hidden=visible!==0;
    };
    search?.addEventListener('input',apply);
    status?.addEventListener('change',apply);
  });

  const onlyDigits=value=>(value||'').replace(/\D/g,'');
  const applyMask=(kind,value)=>{
    let d=onlyDigits(value);
    if(kind==='cpf'){
      d=d.slice(0,11);
      return d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    }
    if(kind==='cnpj'){
      d=d.slice(0,14);
      return d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d{1,2})$/,'$1-$2');
    }
    if(kind==='cep'){
      d=d.slice(0,8);
      return d.replace(/(\d{5})(\d)/,'$1-$2');
    }
    if(kind==='phone'){
      d=d.slice(0,11);
      if(d.length<=10) return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{4})(\d)/,'$1-$2');
      return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2');
    }
    if(kind==='rg'){
      const raw=(value||'').toUpperCase().replace(/[^0-9A-Z]/g,'').slice(0,9);
      return raw.replace(/^(.{2})(.)/,'$1.$2').replace(/^(.{2})\.(.{3})(.)/,'$1.$2.$3').replace(/(.{3})(.)$/,'$1-$2');
    }
    if(kind==='cpfcnpj') return applyMask(d.length<=11?'cpf':'cnpj',d);
    return value;
  };

  qa('[data-mask]').forEach(input=>{
    const update=()=>{ input.value=applyMask(input.dataset.mask,input.value); };
    input.addEventListener('input',update);
    update();
  });

  // CEP: preenche endereço, cidade e UF automaticamente. ViaCEP com fallback BrasilAPI.
  qa('input[name="postal_code"]').forEach(input=>{
    let timer=null;
    let lastCep='';
    const form=input.closest('form')||document;
    const address=q('input[name="address"]',form);
    const city=q('input[name="city"]',form);
    const state=q('input[name="state"]',form);
    const lookup=async()=>{
      const cep=onlyDigits(input.value).slice(0,8);
      if(cep.length!==8||cep===lastCep) return;
      lastCep=cep;
      input.classList.add('cep-loading');
      try{
        let data=null;
        try{
          const r=await fetch('https://viacep.com.br/ws/'+cep+'/json/',{cache:'no-store'});
          if(r.ok){
            const j=await r.json();
            if(!j.erro) data={address:j.logradouro||'',city:j.localidade||'',state:j.uf||''};
          }
        }catch(_){}
        if(!data){
          try{
            const r=await fetch('https://brasilapi.com.br/api/cep/v1/'+cep,{cache:'no-store'});
            if(r.ok){
              const j=await r.json();
              data={address:j.street||'',city:j.city||'',state:j.state||''};
            }
          }catch(_){}
        }
        if(data){
          if(address && data.address) address.value=data.address;
          if(city && data.city) city.value=data.city;
          if(state && data.state) state.value=data.state;
          [address,city,state].forEach(el=>el?.dispatchEvent(new Event('input',{bubbles:true})));
          input.classList.add('cep-found');
          window.setTimeout(()=>input.classList.remove('cep-found'),1200);
        }
      }finally{
        input.classList.remove('cep-loading');
      }
    };
    input.addEventListener('input',()=>{
      window.clearTimeout(timer);
      timer=window.setTimeout(lookup,300);
    });
    input.addEventListener('blur',lookup);
  });

  // Campos editáveis no painel administrativo: duplo clique abre o input e ✓ salva.
  qa('[data-inline-field][data-inline-edit-url]').forEach(cell=>{
    if(cell.dataset.inlineBound==='1') return;
    cell.dataset.inlineBound='1';
    cell.addEventListener('dblclick',()=>{
      if(cell.classList.contains('editing')) return;
      const field=cell.dataset.inlineField||'';
      const type=cell.dataset.inlineType||'text';
      const mask=cell.dataset.inlineMask||'';
      const raw=cell.dataset.inlineRaw||'';
      const display=q('[data-inline-display]',cell);
      if(!display) return;

      cell.classList.add('editing');
      const editor=document.createElement('div');
      editor.className='admin-inline-editor';
      const input=document.createElement('input');
      input.type=type;
      input.value=raw;
      input.name=field;
      if(mask) input.dataset.mask=mask;
      const save=document.createElement('button');
      save.type='button';
      save.className='admin-inline-save';
      save.setAttribute('aria-label','Salvar alteração');
      save.textContent='✓';
      const cancel=document.createElement('button');
      cancel.type='button';
      cancel.className='admin-inline-cancel';
      cancel.setAttribute('aria-label','Cancelar edição');
      cancel.textContent='×';
      editor.append(input,save,cancel);
      display.hidden=true;
      cell.appendChild(editor);

      if(mask){
        const update=()=>{ input.value=applyMask(mask,input.value); };
        input.addEventListener('input',update);
        update();
      }
      input.focus();
      input.select?.();

      const close=()=>{
        editor.remove();
        display.hidden=false;
        cell.classList.remove('editing');
      };
      cancel.addEventListener('click',close);
      input.addEventListener('keydown',event=>{
        if(event.key==='Escape') close();
        if(event.key==='Enter'){event.preventDefault();save.click();}
      });
      save.addEventListener('click',async()=>{
        const csrf=q('input[name="_csrf"]')?.value||'';
        save.disabled=true;
        input.disabled=true;
        try{
          const body=new URLSearchParams({_csrf:csrf,field:field,value:input.value});
          const response=await fetch(cell.dataset.inlineEditUrl,{
            method:'POST',
            body,
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}
          });
          const payload=await response.json().catch(()=>({}));
          if(!response.ok||!payload.ok) throw new Error(payload.error||'Não foi possível salvar.');
          display.textContent=input.value||'—';
          cell.dataset.inlineRaw=input.value;
          cell.classList.add('saved');
          close();
          window.setTimeout(()=>window.location.reload(),350);
        }catch(error){
          window.alert(error?.message||'Não foi possível salvar.');
          input.disabled=false;
          save.disabled=false;
          input.focus();
        }
      });
    });
  });

  // Check-out somente após 15 minutos do check-in.
  qa('[data-checkout-countdown]').forEach(box=>{
    const button=q('[data-checkout-button]')||q('[data-checkout-button]',box.parentElement||document);
    const remaining=q('[data-checkout-remaining]',box);
    const unlockAt=Number(box.dataset.unlockAt||0);
    if(!unlockAt||!remaining||!button) return;
    const tick=()=>{
      const diff=Math.max(0,unlockAt-Date.now());
      if(diff<=0){
        box.hidden=true;
        button.disabled=false;
        button.textContent='Encerrar turno / check-out';
        return false;
      }
      const total=Math.ceil(diff/1000);
      const min=Math.floor(total/60);
      const sec=total%60;
      remaining.textContent=String(min).padStart(2,'0')+':'+String(sec).padStart(2,'0');
      button.disabled=true;
      button.textContent='Check-out liberado em '+remaining.textContent;
      return true;
    };
    if(tick()){
      const id=window.setInterval(()=>{if(!tick())window.clearInterval(id);},1000);
    }
  });

  const bindPasswordToggle=(input,button)=>{
    if(!input||!button||button.dataset.passwordBound==='1') return;
    button.dataset.passwordBound='1';
    button.addEventListener('click',()=>{
      const show=input.type==='password';
      input.type=show?'text':'password';
      button.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');
      button.setAttribute('aria-pressed',show?'true':'false');
      button.classList.toggle('is-visible',show);
      input.focus({preventScroll:true});
    });
  };

  qa('.password-field').forEach(wrapper=>{
    bindPasswordToggle(q('input',wrapper),q('[data-password-toggle]',wrapper));
  });

  qa('input[type="password"]').forEach(input=>{
    if(input.closest('.password-field')) return;

    const wrapper=document.createElement('span');
    wrapper.className='password-field';
    input.parentNode?.insertBefore(wrapper,input);
    wrapper.appendChild(input);

    const button=document.createElement('button');
    button.type='button';
    button.className='password-toggle';
    button.dataset.passwordToggle='';
    button.setAttribute('aria-label','Mostrar senha');
    button.setAttribute('aria-pressed','false');
    button.innerHTML='<svg class="tp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>';
    wrapper.appendChild(button);
    bindPasswordToggle(input,button);
  });

  qa('[data-category-modal]').forEach(modal=>{
    const openButton=q('[data-category-modal-open]');
    const form=q('[data-category-form]',modal);
    const input=q('input[name="name"]',form);
    const submit=q('[data-category-submit]',form);
    const feedback=q('[data-category-feedback]',form);
    const select=q('[data-category-select]');
    const notice=q('[data-category-created-notice]');
    let previousFocus=null;

    const setFeedback=(message='')=>{
      if(!feedback) return;
      feedback.textContent=message;
      feedback.hidden=!message;
    };

    const openModal=()=>{
      previousFocus=document.activeElement;
      setFeedback('');
      modal.hidden=false;
      document.body.classList.add('modal-open');
      window.setTimeout(()=>input?.focus(),0);
    };

    const closeModal=()=>{
      modal.hidden=true;
      document.body.classList.remove('modal-open');
      form?.reset();
      setFeedback('');
      previousFocus?.focus?.();
    };

    openButton?.addEventListener('click',openModal);
    qa('[data-category-modal-close]',modal).forEach(button=>button.addEventListener('click',closeModal));
    modal.addEventListener('click',event=>{ if(event.target===modal) closeModal(); });
    document.addEventListener('keydown',event=>{
      if(event.key==='Escape'&&!modal.hidden) closeModal();
    });

    form?.addEventListener('submit',async event=>{
      event.preventDefault();
      const name=(input?.value||'').trim();
      if(name.length<2){
        setFeedback('Informe um nome de categoria com pelo menos 2 caracteres.');
        input?.focus();
        return;
      }

      submit.disabled=true;
      setFeedback('');
      try{
        const response=await fetch(form.action,{
          method:'POST',
          body:new FormData(form),
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        const raw=await response.text();
        let payload={};
        try{ payload=raw?JSON.parse(raw):{}; }catch(_){ payload={}; }

        if(!response.ok||!payload.ok){
          const message=payload.error||(response.status===419
            ? 'Sua sessão expirou. Atualize a página e tente novamente.'
            : 'Não foi possível cadastrar a categoria.');
          throw new Error(message);
        }

        const category=payload.category||{};
        if(!category.id||!category.name||!select) throw new Error('A categoria foi salva, mas não foi possível selecioná-la.');

        let option=[...select.options].find(item=>String(item.value)===String(category.id));
        if(!option){
          option=new Option(String(category.name),String(category.id));
          select.add(option);
        }else{
          option.textContent=String(category.name);
        }

        select.value=String(category.id);
        select.dispatchEvent(new Event('change',{bubbles:true}));

        if(notice){
          notice.textContent='Categoria “'+String(category.name)+'” cadastrada e selecionada.';
          notice.hidden=false;
          window.setTimeout(()=>{ notice.hidden=true; },5000);
        }

        modal.hidden=true;
        document.body.classList.remove('modal-open');
        form.reset();
        setFeedback('');
        select.focus();
      }catch(error){
        setFeedback(error?.message||'Não foi possível cadastrar a categoria.');
        input?.focus();
      }finally{
        submit.disabled=false;
      }
    });
  });

  qa('form.register-form').forEach(form=>form.addEventListener('submit',event=>{
    const categories=q('.category-checks',form);
    if(categories&&!q('input[type="checkbox"]:checked',categories)){
      event.preventDefault();
      const first=q('input[type="checkbox"]',categories);
      categories.scrollIntoView({behavior:'smooth',block:'center'});
      first?.focus();
      window.alert('Selecione pelo menos uma função de interesse.');
    }
  }));

  qa('input[name="acceptance_mode"]').forEach(input=>input.addEventListener('change',()=>{
    qa('.choice-card').forEach(card=>card.classList.toggle('selected',!!q('input',card)?.checked));
  }));

  qa('[data-global-search]').forEach(form=>{
    const input=q('[data-global-search-input]',form);
    const dropdown=q('[data-global-search-dropdown]',form);
    if(!input||!dropdown) return;

    let timer=null;
    let controller=null;
    let activeIndex=-1;

    const close=()=>{
      dropdown.hidden=true;
      dropdown.replaceChildren();
      activeIndex=-1;
    };

    const itemNodes=()=>qa('.global-search-suggestion',dropdown);

    const setActive=index=>{
      const items=itemNodes();
      if(!items.length){activeIndex=-1;return;}
      activeIndex=(index+items.length)%items.length;
      items.forEach((item,i)=>item.classList.toggle('active',i===activeIndex));
      items[activeIndex]?.scrollIntoView({block:'nearest'});
    };

    const render=(items,query)=>{
      dropdown.replaceChildren();
      activeIndex=-1;

      const head=document.createElement('div');
      head.className='global-search-suggestion-head';
      head.textContent='Resultados para “'+query+'”';
      dropdown.appendChild(head);

      if(!items.length){
        const empty=document.createElement('div');
        empty.className='global-search-suggestion-empty';
        empty.textContent='Nenhum resultado encontrado.';
        dropdown.appendChild(empty);
      }else{
        items.slice(0,8).forEach(item=>{
          const link=document.createElement('a');
          link.className='global-search-suggestion';
          link.href=item.url||'#';

          const badge=document.createElement('span');
          badge.className='global-search-suggestion-type';
          badge.textContent=item.type||'Resultado';

          const copy=document.createElement('span');
          copy.className='global-search-suggestion-copy';
          const title=document.createElement('strong');
          title.textContent=item.title||'';
          const subtitle=document.createElement('small');
          subtitle.textContent=item.subtitle||'';
          copy.append(title,subtitle);

          const meta=document.createElement('span');
          meta.className='global-search-suggestion-meta';
          meta.textContent=item.meta||'';

          link.append(badge,copy,meta);
          dropdown.appendChild(link);
        });
      }

      const all=document.createElement('button');
      all.type='submit';
      all.className='global-search-see-all';
      all.textContent='Ver todos os resultados →';
      dropdown.appendChild(all);
      dropdown.hidden=false;
    };

    const search=async()=>{
      const term=input.value.trim();
      if(term.length<2){close();return;}
      controller?.abort();
      controller=new AbortController();
      try{
        const base=window.TP_BASE||'';
        const response=await fetch(base+'/buscar?q='+encodeURIComponent(term)+'&format=json',{
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store',
          signal:controller.signal
        });
        if(!response.ok) throw new Error('search failed');
        const payload=await response.json();
        if(input.value.trim()!==term) return;
        render(Array.isArray(payload?.data?.items)?payload.data.items:[],term);
      }catch(error){
        if(error?.name!=='AbortError') close();
      }
    };

    input.addEventListener('input',()=>{
      window.clearTimeout(timer);
      timer=window.setTimeout(search,220);
    });
    input.addEventListener('focus',()=>{
      if(input.value.trim().length>=2) search();
    });
    input.addEventListener('keydown',event=>{
      if(dropdown.hidden) return;
      const items=itemNodes();
      if(event.key==='ArrowDown'){
        event.preventDefault();
        setActive(activeIndex+1);
      }else if(event.key==='ArrowUp'){
        event.preventDefault();
        setActive(activeIndex-1);
      }else if(event.key==='Enter'&&activeIndex>=0&&items[activeIndex]){
        event.preventDefault();
        window.location.href=items[activeIndex].href;
      }else if(event.key==='Escape'){
        close();
      }
    });
    form.addEventListener('submit',event=>{
      if(input.value.trim().length<2){
        event.preventDefault();
        input.focus();
        close();
      }
    });
    dropdown.addEventListener('mousedown',event=>event.stopPropagation());
    document.addEventListener('mousedown',event=>{
      if(!form.contains(event.target)) close();
    });
  });

  const notificationRoot=q('[data-notifications]');
  if(notificationRoot){
    const toggle=q('[data-notifications-toggle]',notificationRoot);
    const panel=q('[data-notifications-panel]',notificationRoot);
    const badge=q('[data-notification-badge]',notificationRoot);
    const summary=q('[data-notification-summary]',notificationRoot);
    const list=q('[data-notification-list]',notificationRoot);

    const knownIds=new Set(qa('[data-notification-id]',notificationRoot).map(el=>String(el.dataset.notificationId||'')).filter(Boolean));
    let initialized=true;
    let audioUnlocked=false;
    const unlockAudio=()=>{audioUnlocked=true;};
    document.addEventListener('pointerdown',unlockAudio,{once:true});

    const playNotificationPop=()=>{
      if(!audioUnlocked) return;
      try{
        const Ctx=window.AudioContext||window.webkitAudioContext;
        if(!Ctx) return;
        const ctx=new Ctx();
        const gain=ctx.createGain();
        gain.gain.setValueAtTime(.0001,ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(.15,ctx.currentTime+.01);
        gain.gain.exponentialRampToValueAtTime(.0001,ctx.currentTime+.18);
        const osc=ctx.createOscillator();
        osc.type='sine';
        osc.frequency.setValueAtTime(720,ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(980,ctx.currentTime+.12);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime+.19);
        osc.onended=()=>ctx.close();
      }catch(_){}
    };

    const showNotificationToast=item=>{
      let stack=q('[data-notification-toast-stack]');
      if(!stack){
        stack=document.createElement('div');
        stack.className='notification-toast-stack';
        stack.dataset.notificationToastStack='';
        document.body.appendChild(stack);
      }
      const toast=document.createElement('a');
      toast.className='notification-toast';
      toast.href=item.open_url||((window.TP_BASE||'')+'/notificacoes');
      const title=document.createElement('strong');
      title.textContent=item.title||'Nova notificação';
      const body=document.createElement('span');
      body.textContent=item.body||'';
      toast.append(title,body);
      stack.appendChild(toast);
      requestAnimationFrame(()=>toast.classList.add('show'));
      window.setTimeout(()=>{toast.classList.remove('show');window.setTimeout(()=>toast.remove(),220);},6500);
    };

    const announceNewItems=items=>{
      const fresh=items.filter(item=>!item.read_at && item.id!=null && !knownIds.has(String(item.id)));
      items.forEach(item=>{if(item.id!=null)knownIds.add(String(item.id));});
      if(!fresh.length) return;
      notificationRoot.classList.remove('notification-new');
      void notificationRoot.offsetWidth;
      notificationRoot.classList.add('notification-new');
      window.setTimeout(()=>notificationRoot.classList.remove('notification-new'),5000);
      fresh.slice(0,2).forEach(showNotificationToast);
      playNotificationPop();
    };

    const setOpen=open=>{
      if(!panel||!toggle) return;
      panel.hidden=!open;
      toggle.setAttribute('aria-expanded',open?'true':'false');
      notificationRoot.classList.toggle('open',open);
    };

    toggle?.addEventListener('click',event=>{
      event.stopPropagation();
      setOpen(panel?.hidden!==false);
    });
    panel?.addEventListener('click',event=>event.stopPropagation());
    document.addEventListener('click',()=>setOpen(false));
    document.addEventListener('keydown',event=>{if(event.key==='Escape')setOpen(false);});

    const iconText=type=>{
      if(type==='matching_shift') return 'V';
      if(type==='invitation') return 'C';
      if(type==='application') return 'P';
      if(type==='application_approved') return '✓';
      if(type==='application_rejected') return '!';
      return '•';
    };

    const renderNotifications=data=>{
      if(!data||!badge||!list) return;
      const unread=Number(data.unread||0);
      badge.textContent=unread>99?'99+':String(unread);
      badge.hidden=unread<=0;
      if(summary) summary.textContent=unread+' não lida(s)';

      list.replaceChildren();
      const items=Array.isArray(data.items)?data.items:[];
      announceNewItems(items);
      if(!items.length){
        const empty=document.createElement('div');
        empty.className='notification-empty';
        empty.textContent='Nenhuma notificação por enquanto.';
        list.appendChild(empty);
        return;
      }

      items.forEach(item=>{
        const link=document.createElement('a');
        link.className='notification-item'+(item.read_at?'':' unread');
        if(item.id!=null) link.dataset.notificationId=String(item.id);
        link.href=item.open_url||((window.TP_BASE||'')+'/notificacoes');

        const icon=document.createElement('span');
        icon.className='notification-item-icon notification-item-letter';
        icon.textContent=iconText(item.type||'');

        const copy=document.createElement('span');
        copy.className='notification-item-copy';
        const title=document.createElement('strong');
        title.textContent=item.title||'Notificação';
        const body=document.createElement('span');
        body.textContent=item.body||'';
        const time=document.createElement('small');
        time.textContent=(item.created_at||'').replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2}) /,'$3/$2/$1 ');
        copy.append(title,body,time);
        link.append(icon,copy);
        list.appendChild(link);
      });
    };

    const refreshNotifications=async()=>{
      if(document.hidden) return;
      try{
        const response=await fetch((window.TP_BASE||'')+'/notificacoes/feed',{
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store'
        });
        if(!response.ok) return;
        const payload=await response.json();
        if(payload?.ok) renderNotifications(payload.data);
      }catch(_){}
    };

    window.setInterval(refreshNotifications,20000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshNotifications();});
  }
})();
