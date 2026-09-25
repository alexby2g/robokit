<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div><div class="text-h4 page-title">Reporte económico</div><div class="page-subtitle">Ingresos por ventas entregadas, compras, resultado económico e inventario.</div></div>
      <q-btn outline color="cyan-4" icon="refresh" label="Actualizar" :loading="loading" @click="load"/>
    </div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-lg-3" v-for="m in cards" :key="m.label"><q-card flat class="metric-card q-pa-md"><div class="metric-label">{{m.label}}</div><div class="metric-value" :class="m.className">{{m.value}}</div><div class="text-caption text-muted">{{m.hint}}</div></q-card></div>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-lg-8">
        <q-card flat class="panel">
          <q-card-section><div class="text-h6 text-weight-bold">Resumen económico mensual</div><div class="text-caption text-muted">Solo las ventas con estado Entregado se consideran como ingreso.</div></q-card-section>
          <q-table :rows="months" :columns="columns" row-key="mes" dark flat class="table-dark">
            <template #body-cell-ventas="p"><q-td :props="p" class="text-green-3">Bs {{money(p.row.ventas)}}</q-td></template>
            <template #body-cell-compras="p"><q-td :props="p" class="text-amber-3">Bs {{money(p.row.compras)}}</q-td></template>
            <template #body-cell-resultado="p"><q-td :props="p" :class="result(p.row)>=0?'text-green-3':'text-red-3'">Bs {{money(result(p.row))}}</q-td></template>
          </q-table>
        </q-card>
      </div>
      <div class="col-12 col-lg-4">
        <q-card flat class="panel"><q-card-section><div class="text-h6 text-weight-bold">Alertas de stock</div></q-card-section><q-list separator dark><q-item v-for="p in low" :key="p.id"><q-item-section><q-item-label>{{p.Nombre}}</q-item-label><q-item-label caption>Requiere reposición</q-item-label></q-item-section><q-item-section side><q-chip dense color="red-10" text-color="red-2">{{p.Disponible ?? p.Stock}} u.</q-chip></q-item-section></q-item></q-list><div v-if="!low.length" class="q-pa-lg text-center text-muted">Sin productos críticos.</div></q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import {computed,onMounted,ref} from 'vue'
import api from '../services/api'
const loading=ref(false),data=ref({}),products=ref([])
const money=v=>Number(v||0).toFixed(2)
const load=async()=>{loading.value=true;try{const [a,b]=await Promise.all([api.get('/dashboard/totales'),api.get('/productos')]);data.value=a.data.data||a.data||{};products.value=b.data.productos||[]}finally{loading.value=false}}
const t=computed(()=>data.value.totales||data.value)
const economicResult=computed(()=>Number(t.value.ventas_mes||0)-Number(t.value.compras_mes||0))
const cards=computed(()=>[
  {label:'Ingresos del mes',value:`Bs ${money(t.value.ventas_mes)}`,hint:`${t.value.pedidos_mes||0} ventas entregadas`,className:'text-green-3'},
  {label:'Compras del mes',value:`Bs ${money(t.value.compras_mes)}`,hint:`${t.value.compras_cantidad_mes||0} compras registradas`,className:'text-amber-3'},
  {label:'Resultado económico',value:`Bs ${money(economicResult.value)}`,hint:'Ingresos menos compras',className:economicResult.value>=0?'text-green-3':'text-red-3'},
  {label:'Valor de inventario',value:`Bs ${money(data.value.valor_inventario)}`,hint:'Precio de venta × stock físico',className:''},
])
const months=computed(()=>data.value.meses||data.value.estadisticas_mensuales||[])
const low=computed(()=>data.value.stock_bajo||products.value.filter(p=>Number(p.Disponible??p.Stock)<=5))
const result=row=>Number(row.ventas||0)-Number(row.compras||0)
const columns=[{name:'mes',label:'Mes',field:'mes',align:'left'},{name:'ventas',label:'Ingresos',field:'ventas',align:'right'},{name:'compras',label:'Compras',field:'compras',align:'right'},{name:'resultado',label:'Resultado',field:'resultado',align:'right'}]
onMounted(load)
</script>
