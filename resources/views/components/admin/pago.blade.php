@props(['order'])

@php([$texto, $clases] = $order->badgePago())

<x-admin.badge :clases="$clases">{{ $texto }}</x-admin.badge>
