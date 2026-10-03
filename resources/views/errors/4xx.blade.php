@extends('errors.layout')

@section('code', (string) ($status ?? '4xx'))
@section('title', 'Pedido não concluído')
@section('message', 'Não foi possível concluir este pedido. Volte e tente de outro caminho.')
